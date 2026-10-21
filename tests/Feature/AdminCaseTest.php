<?php

namespace Tests\Feature;

use App\Models\AdminCase;
use App\Models\Employee;
use App\Models\Setting;
use App\Models\User;
use App\Services\DtrBuilder;
use App\Services\PayrollInputs;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminCaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_case_from_complaint_to_suspension_on_dtr_and_pay(): void
    {
        Storage::fake('local');
        Carbon::setTestNow('2026-11-20 09:00:00');
        Setting::put(['session_idle_minutes' => '0', 'attendance_start_date' => '2026-11-01']);
        $hr = User::create(['name' => 'HR', 'username' => 'hr', 'password' => 'secret123', 'role' => 'hr']);
        $e = Employee::create(['employee_no' => 'E1', 'last_name' => 'Cruz', 'first_name' => 'Juan', 'employment_type' => 'permanent', 'monthly_salary' => 22000]);

        $this->actingAs($hr)->post('/admin/cases', ['employee_id' => $e->id, 'offense' => 'Simple neglect of duty', 'complainant' => 'Division Chief', 'date_filed' => '2026-11-02'])->assertRedirect();
        $case = AdminCase::sole();
        $this->assertSame('AC-2026-001', $case->case_no);

        $this->post("/admin/cases/{$case->id}/events", ['date' => '2026-11-03', 'type' => 'show_cause', 'due_on' => '2026-11-10',
            'document' => UploadedFile::fake()->create('order.pdf', 20, 'application/pdf')])->assertSessionHasNoErrors();
        $this->assertSame('preliminary', $case->fresh()->status);
        $this->get('/admin/cases')->assertOk()->assertSee('overdue');
        $this->get('/admin/cases/events/'.$case->events()->where('type', 'show_cause')->sole()->id.'/document')->assertOk();

        $this->post("/admin/cases/{$case->id}/decide", ['penalty' => 'suspension', 'decided_on' => '2026-11-12', 'decision' => 'Suspended for 5 days.',
            'suspension_from' => '2026-11-16', 'suspension_to' => '2026-11-20'])->assertSessionHasNoErrors();
        $this->assertSame('decided', $case->fresh()->status);

        // Mon–Fri Nov 16–20: SUSPENDED on the DTR, not absent, and 5 days off pay.
        $dtr = app(DtrBuilder::class)->build($e, 2026, 11);
        $this->assertSame(5, $dtr['suspended_days']);
        $this->assertSame('SUSPENDED', collect($dtr['rows'])->firstWhere('day', 16)['note']);
        $this->assertSame(10, $dtr['days_absent']); // the other working days of Nov 2–20 without punches
        $row = collect(app(PayrollInputs::class)->rows(2026, 11))->firstWhere('employee_no', 'E1');
        $this->assertSame(round(22000 / 22 * 15, 2), $row['deductions']);

        $this->get("/admin/cases/{$case->id}/print")->assertOk()->assertSee('CONFIDENTIAL')->assertSee('Suspended for 5 days.');

        // Confidential: payroll and auditors can't see cases.
        foreach (['payroll', 'auditor'] as $role) {
            $u = User::create(['name' => $role, 'username' => $role, 'password' => 'secret123', 'role' => $role]);
            $this->actingAs($u)->get('/admin/cases')->assertForbidden();
        }
    }
}
