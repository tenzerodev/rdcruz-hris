<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\PassSlip;
use App\Models\Punch;
use App\Models\Setting;
use App\Models\User;
use App\Services\DtrBuilder;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PassSlipTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private User $boss;

    private Employee $juan;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-04 09:00:00'); // Wednesday
        Setting::put(['session_idle_minutes' => '0', 'attendance_start_date' => '2026-11-02']);
        $this->boss = User::create(['name' => 'Boss', 'username' => 'boss', 'password' => 'secret123', 'role' => 'employee']);
        $bossEmployee = Employee::create(['employee_no' => 'B1', 'last_name' => 'Reyes', 'first_name' => 'Boss', 'schedule_type' => 'fixed', 'user_id' => $this->boss->id]);
        $this->user = User::create(['name' => 'Juan', 'username' => 'juan', 'password' => 'secret123', 'role' => 'employee']);
        $this->juan = Employee::create(['employee_no' => 'E1', 'last_name' => 'Cruz', 'first_name' => 'Juan', 'schedule_type' => 'fixed',
            'user_id' => $this->user->id, 'supervisor_id' => $bossEmployee->id]);
    }

    private function day(string $date, string $in, string $out): void
    {
        Punch::create(['employee_id' => $this->juan->id, 'work_date' => $date, 'type' => 'am_in', 'punched_at' => "$date $in", 'source' => 'kiosk']);
        Punch::create(['employee_id' => $this->juan->id, 'work_date' => $date, 'type' => 'pm_out', 'punched_at' => "$date $out", 'source' => 'kiosk']);
    }

    public function test_request_approve_and_record_times(): void
    {
        $this->actingAs($this->user)->post('/me/pass-slips', ['date' => '2026-11-03', 'type' => 'official', 'time_out' => '14:00',
            'destination' => 'DBM', 'purpose' => 'Submit documents'])->assertSessionHasErrors('date'); // past

        $this->post('/me/pass-slips', ['date' => '2026-11-04', 'type' => 'personal', 'time_out' => '10:00', 'time_back' => '11:00',
            'destination' => 'Bank', 'purpose' => 'Personal errand'])->assertSessionHasNoErrors();
        $slip = PassSlip::sole();

        $this->actingAs($this->boss)->get('/me/approvals')->assertOk()->assertSee('Bank');
        $this->post("/me/approvals/pass-slips/{$slip->id}", ['decision' => 'approve'])->assertSessionHasNoErrors();
        $this->assertSame('approved', $slip->fresh()->status);

        Carbon::setTestNow('2026-11-04 10:05:00');
        $this->actingAs($this->user)->post("/me/pass-slips/{$slip->id}/clock", ['event' => 'back'])->assertStatus(422); // not out yet
        $this->post("/me/pass-slips/{$slip->id}/clock", ['event' => 'out'])->assertSessionHasNoErrors();
        Carbon::setTestNow('2026-11-04 11:20:00');
        $this->post("/me/pass-slips/{$slip->id}/clock", ['event' => 'back'])->assertSessionHasNoErrors();
        $this->assertSame(['10:05', '11:20'], [$slip->fresh()->actual_out, $slip->fresh()->actual_back]);

        // Personal slips are limited per month.
        config(['hris.pass_slips.personal_per_month' => 1]);
        $this->post('/me/pass-slips', ['date' => '2026-11-05', 'type' => 'personal', 'time_out' => '10:00', 'time_back' => '10:30',
            'destination' => 'Clinic', 'purpose' => 'Checkup'])->assertSessionHasErrors('type');
    }

    public function test_dtr_counts_personal_time_as_undertime_and_excuses_official_time(): void
    {
        Carbon::setTestNow('2026-11-10 09:00:00');
        // Personal, 10:00–11:30 → 90 minutes undertime.
        $this->day('2026-11-04', '08:00', '17:00');
        PassSlip::create(['employee_id' => $this->juan->id, 'date' => '2026-11-04', 'type' => 'personal', 'time_out' => '10:00', 'time_back' => '11:30',
            'destination' => 'Bank', 'purpose' => 'x', 'status' => 'approved']);
        // Official, left at 15:00 and did not come back (no PM out punch) → no undertime.
        Punch::create(['employee_id' => $this->juan->id, 'work_date' => '2026-11-05', 'type' => 'am_in', 'punched_at' => '2026-11-05 08:00', 'source' => 'kiosk']);
        Punch::create(['employee_id' => $this->juan->id, 'work_date' => '2026-11-05', 'type' => 'am_out', 'punched_at' => '2026-11-05 12:00', 'source' => 'kiosk']);
        Punch::create(['employee_id' => $this->juan->id, 'work_date' => '2026-11-05', 'type' => 'pm_in', 'punched_at' => '2026-11-05 13:00', 'source' => 'kiosk']);
        PassSlip::create(['employee_id' => $this->juan->id, 'date' => '2026-11-05', 'type' => 'official', 'time_out' => '15:00',
            'destination' => 'DBM', 'purpose' => 'x', 'status' => 'approved']);
        // Personal, not returning, left 15:00 without punching out → 2 hours undertime.
        Punch::create(['employee_id' => $this->juan->id, 'work_date' => '2026-11-06', 'type' => 'am_in', 'punched_at' => '2026-11-06 08:00', 'source' => 'kiosk']);
        PassSlip::create(['employee_id' => $this->juan->id, 'date' => '2026-11-06', 'type' => 'personal', 'time_out' => '15:00',
            'destination' => 'Home', 'purpose' => 'x', 'status' => 'approved']);
        // A pending slip changes nothing.
        $this->day('2026-11-09', '08:00', '17:00');
        PassSlip::create(['employee_id' => $this->juan->id, 'date' => '2026-11-09', 'type' => 'personal', 'time_out' => '09:00', 'time_back' => '10:00',
            'destination' => 'x', 'purpose' => 'x', 'status' => 'pending']);

        $rows = collect(app(DtrBuilder::class)->build($this->juan, 2026, 11)['rows'])->keyBy('day');
        $this->assertSame(90, $rows[4]['undertime']);
        $this->assertSame(0, $rows[5]['undertime']);
        $this->assertSame(120, $rows[6]['undertime']);
        $this->assertSame(0, $rows[9]['undertime']);
    }

    public function test_hr_records_and_corrects_pass_slips(): void
    {
        $tk = User::create(['name' => 'TK', 'username' => 'tk', 'password' => 'secret123', 'role' => 'timekeeper']);
        $this->actingAs($tk)->post('/admin/pass-slips', ['employee_id' => $this->juan->id, 'date' => '2026-11-02', 'type' => 'official',
            'time_out' => '13:30', 'time_back' => '15:00', 'destination' => 'COA', 'purpose' => 'Audit meeting', 'actual_out' => '13:40'])->assertSessionHasNoErrors();
        $slip = PassSlip::sole();
        $this->assertSame(['approved', '13:40'], [$slip->status, $slip->actual_out]);
        $this->put("/admin/pass-slips/{$slip->id}/times", ['actual_out' => '13:40', 'actual_back' => '15:10'])->assertSessionHasNoErrors();
        $this->get('/admin/pass-slips?status=approved')->assertOk()->assertSee('COA')->assertSee('3:10 PM');
    }
}
