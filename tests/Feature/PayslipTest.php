<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\Setting;
use App\Models\User;
use App\Services\PayCalculator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayslipTest extends TestCase
{
    use RefreshDatabase;

    private User $payroll;

    private User $user;

    private Employee $ana;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-05 09:00:00');
        Setting::put(['session_idle_minutes' => '0', 'attendance_start_date' => '2026-11-01']); // no October absences
        $this->payroll = User::create(['name' => 'Pay', 'username' => 'pay', 'password' => 'secret123', 'role' => 'payroll']);
        $this->user = User::create(['name' => 'Ana', 'username' => 'ana', 'password' => 'secret123', 'role' => 'employee']);
        $this->ana = Employee::create(['employee_no' => 'E1', 'last_name' => 'Santos', 'first_name' => 'Ana', 'employment_type' => 'permanent',
            'monthly_salary' => 52000, 'position' => 'Administrative Officer V', 'user_id' => $this->user->id]);
    }

    public function test_calculator_matches_the_payroll_library(): void
    {
        $calc = new PayCalculator;
        $this->assertSame(['employee' => 1300.0, 'employer' => 1300.0], $calc::philhealth(52000));
        $this->assertSame(200.0, $calc::pagibig(52000)['employee']);
        $this->assertSame(4372.4, $calc::withholdingTax(45820));
        $this->assertSame(0.0, $calc::withholdingTax(20000));

        $slip = $calc->payslip(['salary' => 52000, 'attendance_deduction' => 0, 'earned' => 52000, 'gsis_member' => true, 'contributions' => true, 'divisor' => 1,
            'allowances' => [['label' => 'PERA', 'amount' => 2000, 'taxable' => false]]]);
        $this->assertSame(54000.0, $slip['gross']);
        $this->assertSame(4372.4, $slip['tax']);
        $this->assertSame(54000 - 4680 - 1300 - 200 - 4372.4, $slip['net']);
    }

    public function test_payroll_draft_adjust_release_and_employee_views_payslip(): void
    {
        $this->actingAs($this->payroll)->post('/admin/payroll/runs', ['period' => '2026-10', 'cut' => 'full', 'pay_date' => '2026-10-30'])->assertSessionHasNoErrors();
        $run = PayrollRun::sole();
        $slip = Payslip::sole();
        $this->assertSame(54000.0, $slip->gross);
        $this->assertSame(43447.6, $slip->net);

        // Draft: the employee can't see it yet.
        $this->actingAs($this->user)->get('/me/payslips')->assertOk()->assertSee('No payslips yet');
        $this->actingAs($this->user)->get("/me/payslips/{$slip->id}")->assertNotFound();

        // Payroll adds a loan and an honorarium; tax is recomputed on the taxable honorarium.
        $this->actingAs($this->payroll)->put("/admin/payslips/{$slip->id}", [
            'earnings' => [['label' => 'Honorarium', 'amount' => 3000, 'taxable' => 1]],
            'deductions' => [['label' => 'GSIS Multi-Purpose Loan', 'amount' => 2500]],
        ])->assertSessionHasNoErrors();
        $slip->refresh();
        $this->assertSame(57000.0, $slip->gross);
        $this->assertSame(4972.4, $slip->tax); // (45820 + 3000 - 33333) × 20% + 1875
        $this->assertSame(57000 - 4680 - 1300 - 200 - 4972.4 - 2500, $slip->net);

        $this->post("/admin/payroll/runs/{$run->id}/release")->assertSessionHasNoErrors();
        $this->put("/admin/payslips/{$slip->id}", ['deductions' => []])->assertSessionHasErrors('run'); // released: locked

        $this->actingAs($this->user)->get('/me/payslips')->assertOk()->assertSee('October 2026')->assertSee('43,347.60');
        $this->get("/me/payslips/{$slip->id}")->assertOk()->assertSee('PAYSLIP')->assertSee('GSIS Multi-Purpose Loan')
            ->assertSee('43,347.60')->assertSee('Verify this document');
        $this->assertNotNull($slip->fresh()->viewed_at);
    }

    public function test_payslips_are_private(): void
    {
        $boss = User::create(['name' => 'Boss', 'username' => 'boss', 'password' => 'secret123', 'role' => 'employee']);
        $bossEmp = Employee::create(['employee_no' => 'E2', 'last_name' => 'Reyes', 'first_name' => 'Boss', 'user_id' => $boss->id, 'monthly_salary' => 80000]);
        $this->ana->update(['supervisor_id' => $bossEmp->id]);
        $hr = User::create(['name' => 'HR', 'username' => 'hr', 'password' => 'secret123', 'role' => 'hr']);
        $timekeeper = User::create(['name' => 'TK', 'username' => 'tk', 'password' => 'secret123', 'role' => 'timekeeper']);

        $this->actingAs($this->payroll)->post('/admin/payroll/runs', ['period' => '2026-10', 'cut' => 'full']);
        $run = PayrollRun::sole();
        $this->post("/admin/payroll/runs/{$run->id}/release");
        $slip = Payslip::where('employee_id', $this->ana->id)->sole();

        $this->actingAs($boss)->get("/me/payslips/{$slip->id}")->assertNotFound();       // supervisor: no
        $this->actingAs($timekeeper)->get("/me/payslips/{$slip->id}")->assertNotFound(); // no payroll access
        $this->actingAs($hr)->get("/me/payslips/{$slip->id}")->assertOk();               // HR views payroll (read-only)
        $this->actingAs($hr)->post("/admin/payroll/runs/{$run->id}/withdraw")->assertForbidden();
    }

    public function test_semi_monthly_halves_and_duplicate_periods(): void
    {
        $this->actingAs($this->payroll)->post('/admin/payroll/runs', ['period' => '2026-10', 'cut' => 'first'])->assertSessionHasNoErrors();
        $this->assertSame(27000.0, Payslip::sole()->gross);
        $this->assertSame(2186.1, Payslip::sole()->tax); // semi-monthly table on 22,910
        $this->post('/admin/payroll/runs', ['period' => '2026-10', 'cut' => 'full'])->assertSessionHasErrors('period');
        $this->post('/admin/payroll/runs', ['period' => '2026-10', 'cut' => 'second'])->assertSessionHasNoErrors();
        $this->get('/admin/payroll/runs')->assertOk()->assertSee('October 1–15, 2026')->assertSee('October 16–31, 2026');
    }
}
