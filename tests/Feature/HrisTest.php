<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveApplication;
use App\Models\LeaveLedger;
use App\Models\OvertimeAuthorization;
use App\Models\Position;
use App\Models\Punch;
use App\Models\Setting;
use App\Models\TravelOrder;
use App\Models\User;
use App\Services\DtrBuilder;
use App\Services\LeaveService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HrisTest extends TestCase
{
    use RefreshDatabase;

    private User $hr;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-26 09:00:00'); // a Monday; October 2026 is in the past
        $this->hr = User::create(['name' => 'HR Officer', 'username' => 'hr', 'password' => 'secret123', 'role' => 'admin']);
    }

    private function person(string $type = 'permanent', array $attrs = [], bool $login = false): Employee
    {
        static $n = 0;
        $n++;
        $e = Employee::create(['employee_no' => "E$n", 'last_name' => "Santos$n", 'first_name' => 'Ana', 'employment_type' => $type,
            'schedule_type' => 'flexi', 'position' => 'Officer', 'office' => 'HR', ...$attrs]);
        if ($login) {
            $u = User::create(['name' => $e->display_name, 'username' => "user$n", 'password' => 'secret123', 'role' => 'employee']);
            $e->update(['user_id' => $u->id]);
        }

        return $e->fresh();
    }

    private function punch(Employee $e, string $date, string $in, string $out): void
    {
        Punch::create(['employee_id' => $e->id, 'work_date' => $date, 'type' => 'am_in', 'punched_at' => "$date $in", 'source' => 'kiosk']);
        Punch::create(['employee_id' => $e->id, 'work_date' => $date, 'type' => 'pm_out', 'punched_at' => "$date $out", 'source' => 'kiosk']);
    }

    /** Full attendance Mon–Fri of the week starting $monday (8:00–17:00). */
    private function fullWeek(Employee $e, string $monday, array $skip = []): void
    {
        for ($i = 0; $i < 5; $i++) {
            $d = Carbon::parse($monday)->addDays($i)->toDateString();
            if (! in_array($d, $skip, true)) {
                $this->punch($e, $d, '08:00', '17:00');
            }
        }
    }

    private function approveOt(Employee $e, string $from, ?string $to = null, ?int $max = null): void
    {
        OvertimeAuthorization::create(['employee_id' => $e->id, 'date_from' => $from, 'date_to' => $to ?? $from, 'purpose' => 'Fair setup',
            'max_minutes_per_day' => $max, 'status' => 'approved']);
    }

    private function row(array $dtr, string $date): array
    {
        return collect($dtr['rows'])->first(fn ($r) => $r['date']?->toDateString() === $date);
    }

    public function test_flexi_time_undertime(): void
    {
        $b = app(DtrBuilder::class);
        $s = $b->settings(); // 07:00–10:00 window, 480 min + 60 lunch
        $t = fn (?string $in, ?string $out, ?string $lo = null, ?string $li = null) => [
            'am_in' => $in ? Carbon::parse("2026-10-19 $in") : null, 'am_out' => $lo ? Carbon::parse("2026-10-19 $lo") : null,
            'pm_in' => $li ? Carbon::parse("2026-10-19 $li") : null, 'pm_out' => $out ? Carbon::parse("2026-10-19 $out") : null,
        ];

        $this->assertSame([0, false], $b->flexiUndertime($t('07:00', '16:00'), $s));   // earliest
        $this->assertSame([0, false], $b->flexiUndertime($t('09:30', '18:30'), $s));   // within window
        $this->assertSame([30, false], $b->flexiUndertime($t('09:30', '18:00'), $s));  // left 30 min early
        $this->assertSame([0, false], $b->flexiUndertime($t('06:30', '16:00'), $s));   // before 7:00 counts from 7:00
        $this->assertSame([30, false], $b->flexiUndertime($t('06:30', '15:30'), $s));
        $this->assertSame([15, false], $b->flexiUndertime($t('10:15', '19:00'), $s));  // late 15, required out capped at 19:00
        $this->assertSame([45, false], $b->flexiUndertime($t('10:15', '18:30'), $s));
        $this->assertSame([20, false], $b->flexiUndertime($t('08:00', '17:00', '12:00', '13:20'), $s)); // long lunch
        $this->assertSame([0, true], $b->flexiUndertime($t('08:00', null), $s));       // no time-out
    }

    public function test_overtime_needs_two_hours_approval_and_eligibility(): void
    {
        $e = $this->person();
        $this->punch($e, '2026-10-05', '08:00', '19:30'); // 2h30 after required 17:00
        $this->punch($e, '2026-10-06', '08:00', '18:30'); // 1h30: below the 2-hour minimum
        $this->punch($e, '2026-10-07', '09:00', '20:00'); // 2h, not approved
        $this->approveOt($e, '2026-10-05', '2026-10-06', 120);

        $dtr = app(DtrBuilder::class)->build($e, 2026, 10);
        $this->assertSame([150, 120, 'Over the approved hours'], [$this->row($dtr, '2026-10-05')['ot_actual'], $this->row($dtr, '2026-10-05')['ot_credited'], $this->row($dtr, '2026-10-05')['ot_reason']]);
        $this->assertSame(0, $this->row($dtr, '2026-10-06')['ot_actual']);
        $this->assertSame([120, 0, 'No approved overtime request'], [$this->row($dtr, '2026-10-07')['ot_actual'], $this->row($dtr, '2026-10-07')['ot_credited'], $this->row($dtr, '2026-10-07')['ot_reason']]);

        // COS: no overtime credit even if "approved".
        $cos = $this->person('cos');
        $this->punch($cos, '2026-10-05', '08:00', '20:00');
        $this->approveOt($cos, '2026-10-05');
        $row = $this->row(app(DtrBuilder::class)->build($cos, 2026, 10), '2026-10-05');
        $this->assertSame([180, 0], [$row['ot_actual'], $row['ot_credited']]);
        $this->assertStringContainsString('Not eligible', $row['ot_reason']);
    }

    public function test_weekend_overtime_requires_no_leave_or_absence_that_week(): void
    {
        // Week of Oct 5–9; Saturday Oct 10 and Sunday Oct 11.
        $full = $this->person();
        $this->fullWeek($full, '2026-10-05');
        $this->punch($full, '2026-10-10', '08:00', '12:00');
        $this->approveOt($full, '2026-10-10');
        $this->assertSame(240, $this->row(app(DtrBuilder::class)->build($full, 2026, 10), '2026-10-10')['ot_credited']);

        $absent = $this->person();
        $this->fullWeek($absent, '2026-10-05', skip: ['2026-10-07']);
        $this->punch($absent, '2026-10-10', '08:00', '12:00');
        $this->approveOt($absent, '2026-10-10');
        $row = $this->row(app(DtrBuilder::class)->build($absent, 2026, 10), '2026-10-10');
        $this->assertSame([240, 0], [$row['ot_actual'], $row['ot_credited']]);
        $this->assertStringContainsString('Absent Oct 7', $row['ot_reason']);

        $onLeave = $this->person();
        $this->fullWeek($onLeave, '2026-10-05', skip: ['2026-10-09']);
        LeaveApplication::create(['employee_id' => $onLeave->id, 'type' => 'VL', 'date_from' => '2026-10-09', 'date_to' => '2026-10-09', 'days' => 1, 'status' => 'approved']);
        $this->punch($onLeave, '2026-10-11', '09:00', '15:00'); // Sunday
        $this->approveOt($onLeave, '2026-10-11');
        $row = $this->row(app(DtrBuilder::class)->build($onLeave, 2026, 10), '2026-10-11');
        $this->assertSame(0, $row['ot_credited']);
        $this->assertStringContainsString('On leave (VL) Oct 9', $row['ot_reason']);

        // An approved OB on a weekday is not an absence; a holiday in the week is skipped.
        $ob = $this->person();
        $this->fullWeek($ob, '2026-10-05', skip: ['2026-10-06', '2026-10-08']);
        Holiday::create(['date' => '2026-10-08', 'name' => 'Local holiday']);
        $order = TravelOrder::create(['type' => 'ob', 'date_from' => '2026-10-06', 'date_to' => '2026-10-06', 'destination' => 'PICC', 'purpose' => 'Meeting', 'status' => 'approved']);
        $order->employees()->attach($ob->id);
        $this->punch($ob, '2026-10-10', '08:00', '12:00');
        $this->approveOt($ob, '2026-10-10');
        $dtr = app(DtrBuilder::class)->build($ob, 2026, 10);
        $this->assertSame(240, $this->row($dtr, '2026-10-10')['ot_credited']);
        $this->assertSame('OFFICIAL BUSINESS', $this->row($dtr, '2026-10-06')['note']);
        $this->assertFalse($this->row($dtr, '2026-10-06')['absent']);

        // The rule can be switched off.
        Setting::put(['ot_weekend_full_week' => '0']);
        $this->assertSame(240, $this->row(app(DtrBuilder::class)->build($absent, 2026, 10), '2026-10-10')['ot_credited']);
    }

    public function test_leave_flow_supervisor_then_hr_with_credits(): void
    {
        $boss = $this->person('permanent', [], login: true);
        $ana = $this->person('permanent', ['supervisor_id' => $boss->id], login: true);
        LeaveLedger::create(['employee_id' => $ana->id, 'balance' => 'VL', 'entry_date' => '2026-01-01', 'particulars' => 'Beginning', 'earned' => 2, 'source' => 'adjustment']);

        // Thu Oct 29 – Tue Nov 3 = 4 work days (weekend skipped).
        $this->actingAs($ana->user)->post('/me/leave', ['type' => 'VL', 'date_from' => '2026-10-29', 'date_to' => '2026-11-03', 'details' => ['location' => 'ph']])
            ->assertSessionHasNoErrors();
        $leave = LeaveApplication::sole();
        $this->assertSame(4.0, $leave->days);

        // Only the supervisor (or HR) can recommend.
        $other = $this->person('permanent', [], login: true);
        $this->actingAs($other->user)->post("/me/approvals/leave/{$leave->id}", ['decision' => 'recommend'])->assertForbidden();
        $this->actingAs($boss->user)->get('/me/approvals')->assertOk()->assertSee($ana->full_name);
        $this->actingAs($boss->user)->post("/me/approvals/leave/{$leave->id}", ['decision' => 'recommend'])->assertSessionHasNoErrors();
        $this->assertSame('recommended', $leave->fresh()->status);

        $this->actingAs($this->hr)->post("/admin/leave/{$leave->id}/decide", ['decision' => 'approve'])->assertSessionHasNoErrors();
        $leave->refresh();
        $this->assertSame([2.0, 2.0], [$leave->days_with_pay, $leave->days_without_pay]); // only 2 VL available
        $this->assertSame(0.0, app(LeaveService::class)->balances($ana)['VL']);

        $this->actingAs($ana->user)->get("/print/leave/{$leave->id}")->assertOk()->assertSee('APPLICATION FOR LEAVE')->assertSee('Vacation Leave');
        $this->actingAs($other->user)->get("/print/leave/{$leave->id}")->assertForbidden();

        // Cancelling restores the credits.
        $this->actingAs($this->hr)->post("/admin/leave/{$leave->id}/decide", ['decision' => 'cancel']);
        $this->assertSame(2.0, app(LeaveService::class)->balances($ana)['VL']);
    }

    public function test_cos_and_service_providers_have_no_leave_credits(): void
    {
        $cos = $this->person('cos', ['contract_start' => '2026-07-01', 'contract_end' => '2026-11-15'], login: true);
        $this->actingAs($cos->user)->post('/me/leave', ['type' => 'VL', 'date_from' => '2026-10-28', 'date_to' => '2026-10-28'])->assertSessionHasErrors('type');
        $this->actingAs($cos->user)->post('/me/leave', ['type' => 'OTHERS', 'date_from' => '2026-10-28', 'date_to' => '2026-10-28', 'details' => ['other' => 'Personal']])->assertSessionHasNoErrors();
        $this->actingAs($cos->user)->post('/me/overtime', ['date_from' => '2026-10-28', 'date_to' => '2026-10-28', 'hours' => 2, 'purpose' => 'x'])->assertForbidden();
        $this->assertTrue($cos->contractEndsSoon());
        $this->actingAs($this->hr)->get('/admin')->assertOk()->assertSee('Contracts ending soon')->assertSee($cos->display_name);
    }

    public function test_month_posting_accrues_charges_undertime_and_coc(): void
    {
        $e = $this->person('permanent', ['date_hired' => '2020-01-01']);
        $this->punch($e, '2026-10-01', '08:00', '16:30');       // 30 min undertime
        $this->punch($e, '2026-10-02', '08:00', '20:00');       // 3h OT, approved
        $this->approveOt($e, '2026-10-02');
        $sp = $this->person('service_provider', ['agency_name' => 'Guard Force Inc.']);
        $this->punch($sp, '2026-10-01', '08:00', '16:00');

        $leaves = app(LeaveService::class);
        $leaves->postMonth(2026, 10, $this->hr);
        $leaves->postMonth(2026, 10, $this->hr); // idempotent

        $b = $leaves->balances($e);
        $this->assertEqualsWithDelta(1.25 - 30 / 480, $b['VL'], 0.001);
        $this->assertSame(1.25, $b['SL']);
        $this->assertSame(3.0, $b['COC']);
        $this->assertSame(0, LeaveLedger::where('employee_id', $sp->id)->count());

        $this->actingAs($this->hr)->get("/admin/employees/{$e->id}/leave-card?year=2026")->assertOk()->assertSee('Earned October 2026')->assertSee('COC earned');
    }

    public function test_year_end_forced_leave_and_spl(): void
    {
        $e = $this->person();
        LeaveLedger::create(['employee_id' => $e->id, 'balance' => 'VL', 'entry_date' => '2026-01-01', 'particulars' => 'Beginning', 'earned' => 15, 'source' => 'adjustment']);
        LeaveLedger::create(['employee_id' => $e->id, 'balance' => 'SPL', 'entry_date' => '2026-01-01', 'particulars' => 'SPL', 'earned' => 3, 'source' => 'adjustment']);
        LeaveApplication::create(['employee_id' => $e->id, 'type' => 'FL', 'date_from' => '2026-06-01', 'date_to' => '2026-06-02', 'days' => 2, 'days_with_pay' => 2, 'status' => 'approved']);

        app(LeaveService::class)->postYearEnd(2026, $this->hr);
        app(LeaveService::class)->postYearEnd(2026, $this->hr);

        $b = app(LeaveService::class)->balances($e);
        $this->assertSame(12.0, $b['VL']);  // 3 forced-leave days not taken deducted
        $this->assertSame(3.0, $b['SPL']);  // 3 lapsed, 3 granted for 2027
    }

    public function test_overtime_request_flow_and_weekend_block(): void
    {
        $boss = $this->person('permanent', [], login: true);
        $ana = $this->person('permanent', ['supervisor_id' => $boss->id], login: true);
        $this->fullWeek($ana, '2026-10-19', skip: ['2026-10-21']);

        // Saturday after an absence on Wednesday: refused right away.
        $this->actingAs($ana->user)->post('/me/overtime', ['date_from' => '2026-10-24', 'date_to' => '2026-10-24', 'hours' => 4, 'purpose' => 'Booth build'])
            ->assertSessionHasErrors('date_from');
        // Less than 2 hours: refused.
        $this->actingAs($ana->user)->post('/me/overtime', ['date_from' => '2026-10-27', 'date_to' => '2026-10-27', 'hours' => 1.5, 'purpose' => 'Report'])
            ->assertSessionHasErrors('hours');
        $this->actingAs($ana->user)->post('/me/overtime', ['date_from' => '2026-10-27', 'date_to' => '2026-10-27', 'hours' => 3, 'purpose' => 'Report'])
            ->assertSessionHasNoErrors();

        $ot = OvertimeAuthorization::sole();
        $this->assertSame('pending', $ot->status);
        $this->actingAs($boss->user)->post("/me/approvals/overtime/{$ot->id}", ['decision' => 'approve'])->assertSessionHasNoErrors();
        $this->assertSame('approved', $ot->fresh()->status);
    }

    public function test_hr_pages_201_and_prints_render(): void
    {
        Storage::fake('local');
        $pos = Position::create(['item_no' => 'CITEM-AO2-1', 'title' => 'Administrative Officer II', 'salary_grade' => 15]);
        $this->actingAs($this->hr)->post('/admin/employees', [
            'employee_no' => '2026-001', 'employment_type' => 'permanent', 'schedule_type' => 'flexi', 'last_name' => 'Reyes', 'first_name' => 'Maria',
            'position_id' => $pos->id, 'position' => 'Administrative Officer II', 'salary_grade' => 15, 'step' => 1, 'monthly_salary' => 40000,
            'is_active' => 1, 'username' => 'mreyes', 'password' => 'password123',
        ])->assertSessionHasNoErrors();
        $e = Employee::where('employee_no', '2026-001')->sole();
        $this->assertSame($pos->id, $e->position_id);

        $this->actingAs($this->hr)->post("/admin/employees/{$e->id}/records/education", ['level' => 'college', 'school' => 'UP Diliman', 'degree' => 'BS Economics'])->assertSessionHasNoErrors();
        $this->actingAs($this->hr)->post("/admin/employees/{$e->id}/records/eligibility", ['name' => 'Career Service Professional', 'rating' => '85.50'])->assertSessionHasNoErrors();
        $this->actingAs($this->hr)->post("/admin/employees/{$e->id}/records/training", ['title' => 'Records Management', 'date_from' => '2026-03-01', 'hours' => 16])->assertSessionHasNoErrors();
        $this->actingAs($this->hr)->post("/admin/employees/{$e->id}/records/service", ['date_from' => '2026-01-02', 'designation' => 'Administrative Officer II', 'status' => 'Permanent', 'annual_salary' => 480000])->assertSessionHasNoErrors();
        $this->actingAs($this->hr)->post("/admin/employees/{$e->id}/documents", ['category' => 'appointment', 'title' => 'Appointment', 'file' => UploadedFile::fake()->create('appt.pdf', 100, 'application/pdf')])->assertSessionHasNoErrors();

        foreach (['overview', '201', 'service', 'documents', 'leave'] as $tab) {
            $this->actingAs($this->hr)->get("/admin/employees/{$e->id}?tab=$tab")->assertOk();
        }
        foreach (['/admin', '/admin/employees?group=cos', '/admin/employees/create?type=dbp', '/admin/positions', '/admin/leave', '/admin/leave/posting',
            '/admin/travel', '/admin/overtime', '/admin/reports/workforce', '/admin/settings', '/admin/holidays',
            "/print/service-record/{$e->id}", "/print/certificate/{$e->id}?purpose=loan"] as $url) {
            $this->actingAs($this->hr)->get($url)->assertOk();
        }
        $this->actingAs($this->hr)->get("/print/service-record/{$e->id}")->assertSee('SERVICE RECORD')->assertSee('Administrative Officer II');
        $this->actingAs($this->hr)->get("/print/certificate/{$e->id}?purpose=loan")->assertSee('CERTIFICATE OF EMPLOYMENT')->assertSee('for loan');

        // HR set the password, so the employee must choose their own first.
        $this->actingAs($e->fresh()->user)->get('/me')->assertRedirect(route('account.security'));
        $this->put('/account/password', ['current_password' => 'password123', 'password' => 'my own pass 2026', 'password_confirmation' => 'my own pass 2026'])->assertSessionHasNoErrors();
        foreach (['/me', '/me/leave', '/me/overtime', '/me/travel', '/me/profile'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get('/me/approvals')->assertOk();
        $this->get("/print/documents/{$e->documents()->first()->id}")->assertOk();
        $this->post('/me/travel', ['type' => 'ob', 'date_from' => '2026-10-27', 'date_to' => '2026-10-27', 'time_from' => '13:00', 'time_to' => '17:00', 'destination' => 'DTI', 'purpose' => 'Meeting'])->assertSessionHasNoErrors();

        $order = TravelOrder::sole();
        $this->actingAs($this->hr)->post("/admin/travel/{$order->id}/decide", ['decision' => 'approve']);
        $this->actingAs($this->hr)->get("/print/travel/{$order->id}")->assertOk()->assertSee('OFFICIAL BUSINESS');
    }
}
