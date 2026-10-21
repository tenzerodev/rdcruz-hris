<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Ipcr;
use App\Models\LeaveApplication;
use App\Models\LeaveLedger;
use App\Models\OvertimeAuthorization;
use App\Models\PerformancePeriod;
use App\Models\Punch;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\HrisNotice;
use App\Services\LeaveService;
use App\Services\PayrollInputs;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class HrisExtrasTest extends TestCase
{
    use RefreshDatabase;

    private User $hr;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-26 09:00:00');
        $this->hr = User::create(['name' => 'HR Officer', 'username' => 'hr', 'password' => 'secret123', 'role' => 'admin']);
    }

    private function person(string $type = 'permanent', array $attrs = [], bool $login = false): Employee
    {
        static $n = 0;
        $n++;
        $e = Employee::create(['employee_no' => "X$n", 'last_name' => "Lopez$n", 'first_name' => 'Ben', 'employment_type' => $type, 'schedule_type' => 'flexi',
            'position' => 'Officer', 'office' => 'Admin', 'email' => "ben$n@example.test", ...$attrs]);
        if ($login) {
            $u = User::create(['name' => $e->display_name, 'username' => "u$n", 'password' => 'secret123', 'role' => 'employee']);
            $e->update(['user_id' => $u->id]);
        }

        return $e->fresh();
    }

    private function punch(Employee $e, string $date, string $in, string $out): void
    {
        Punch::create(['employee_id' => $e->id, 'work_date' => $date, 'type' => 'am_in', 'punched_at' => "$date $in", 'source' => 'kiosk']);
        Punch::create(['employee_id' => $e->id, 'work_date' => $date, 'type' => 'pm_out', 'punched_at' => "$date $out", 'source' => 'kiosk']);
    }

    /** Every work day of October 2026 present 8:00–17:00, except the given dates. */
    private function fullMonth(Employee $e, array $skip = []): void
    {
        for ($d = Carbon::parse('2026-10-01'); $d->month === 10; $d->addDay()) {
            if (! $d->isWeekend() && ! in_array($d->toDateString(), $skip, true)) {
                $this->punch($e, $d->toDateString(), '08:00', '17:00');
            }
        }
    }

    public function test_coc_lapses_after_twelve_months_oldest_first(): void
    {
        $e = $this->person();
        LeaveLedger::create(['employee_id' => $e->id, 'balance' => 'COC', 'entry_date' => '2025-10-31', 'period' => '2025-10', 'particulars' => 'COC earned October 2025', 'earned' => 10, 'source' => 'coc']);
        LeaveLedger::create(['employee_id' => $e->id, 'balance' => 'COC', 'entry_date' => '2026-03-31', 'period' => '2026-03', 'particulars' => 'COC earned March 2026', 'earned' => 6, 'source' => 'coc']);
        LeaveLedger::create(['employee_id' => $e->id, 'balance' => 'COC', 'entry_date' => '2026-05-10', 'particulars' => 'CTO', 'used' => 4, 'source' => 'leave']);

        app(LeaveService::class)->postMonth(2026, 10);
        app(LeaveService::class)->postMonth(2026, 10); // re-run replaces

        // 10 earned Oct 2025, 4 used (taken from the oldest) → 6 lapse; March's 6 remain.
        $this->assertSame(6.0, LeaveLedger::where('source', 'coc_lapse')->sole()->used);
        $this->assertSame(6.0, app(LeaveService::class)->balances($e)['COC']);
    }

    public function test_emails_go_to_supervisor_hr_and_employee(): void
    {
        Notification::fake();
        Setting::put(['hr_email' => 'hr@agency.test']);
        $boss = $this->person('permanent', ['email' => 'boss@agency.test'], login: true);
        $ana = $this->person('permanent', ['supervisor_id' => $boss->id, 'email' => 'ana@agency.test'], login: true);
        LeaveLedger::create(['employee_id' => $ana->id, 'balance' => 'VL', 'entry_date' => '2026-01-01', 'particulars' => 'Beg', 'earned' => 5, 'source' => 'adjustment']);

        // Users with a login get the notice in the HRIS and by email; other addresses by email only.
        $sentTo = function (string $email, string $contains) {
            $user = User::all()->first(fn ($u) => $u->routeNotificationForMail() === $email);
            if ($user) {
                return Notification::assertSentTo($user, HrisNotice::class, fn ($n, $channels) => in_array('mail', $channels, true) && in_array('database', $channels, true) && str_contains($n->subject, $contains));
            }
            Notification::assertSentTo(new AnonymousNotifiable, HrisNotice::class, function ($n, $channels, $notifiable) use ($email, $contains) {
                return ($notifiable->routes['mail'] ?? null) === $email && str_contains($n->subject, $contains);
            });
        };
        $emails = fn () => count(Notification::sent(new AnonymousNotifiable, HrisNotice::class))
            + User::all()->sum(fn ($u) => count(Notification::sent($u, HrisNotice::class, fn ($n, $channels) => in_array('mail', $channels, true))));

        $this->actingAs($ana->user)->post('/me/leave', ['type' => 'VL', 'date_from' => '2026-11-02', 'date_to' => '2026-11-02']);
        $sentTo('boss@agency.test', 'Leave application');
        $leave = LeaveApplication::sole();
        $this->actingAs($boss->user)->post("/me/approvals/leave/{$leave->id}", ['decision' => 'recommend']);
        $sentTo('hr@agency.test', 'Leave for approval');
        $this->actingAs($this->hr)->post("/admin/leave/{$leave->id}/decide", ['decision' => 'approve']);
        $sentTo('ana@agency.test', 'leave is approved');

        $this->actingAs($ana->user)->post('/me/overtime', ['date_from' => '2026-10-28', 'date_to' => '2026-10-28', 'hours' => 2, 'purpose' => 'Report']);
        $sentTo('boss@agency.test', 'Overtime request');
        $this->actingAs($boss->user)->post('/me/approvals/overtime/'.OvertimeAuthorization::sole()->id, ['decision' => 'approve']);
        $sentTo('ana@agency.test', 'overtime request was approved');

        // Turned off: nothing more is sent.
        Setting::put(['notifications_enabled' => '0']);
        $count = $emails();
        $this->actingAs($ana->user)->post('/me/travel', ['type' => 'ob', 'date_from' => '2026-10-29', 'date_to' => '2026-10-29', 'destination' => 'DTI', 'purpose' => 'Meeting']);
        $this->assertSame($count, $emails());
        Notification::assertSentTo($boss->user, HrisNotice::class, fn ($n, $channels) => $channels === ['database'] && str_contains($n->subject, 'Official Business'));
    }

    public function test_contract_digest_emails_hr(): void
    {
        Notification::fake();
        Setting::put(['hr_email' => 'hr@agency.test']);
        $this->person('cos', ['contract_end' => '2026-11-10']);
        $this->artisan('hris:contract-digest')->assertSuccessful();
        Notification::assertSentTo(new AnonymousNotifiable, HrisNotice::class, fn ($n) => str_contains($n->subject, 'Contracts ending'));
    }

    public function test_payroll_inputs_from_attendance(): void
    {
        // Plantilla: 1 unauthorized absence, 1 day LWOP; ₱44,000 → ₱2,000 a day.
        $p = $this->person('permanent', ['monthly_salary' => 44000]);
        $this->fullMonth($p, skip: ['2026-10-14', '2026-10-15']);
        LeaveApplication::create(['employee_id' => $p->id, 'type' => 'VL', 'date_from' => '2026-10-15', 'date_to' => '2026-10-15', 'days' => 1,
            'days_with_pay' => 0, 'days_without_pay' => 1, 'status' => 'approved']);

        // COS: ₱1,200/day, 20 days worked, one day 2 hours short.
        $cos = $this->person('cos', ['daily_rate' => 1200]);
        $this->fullMonth($cos, skip: ['2026-10-20', '2026-10-21']);
        Punch::where('employee_id', $cos->id)->whereDate('work_date', '2026-10-22')->where('type', 'pm_out')->update(['punched_at' => '2026-10-22 15:00']);

        $sp = $this->person('service_provider', ['agency_name' => 'Guard Co.']);
        $this->fullMonth($sp);

        $rows = collect(app(PayrollInputs::class)->rows(2026, 10))->keyBy('employee_no');
        $this->assertSame([1.0, 1, 4000.0, 40000.0], [$rows[$p->employee_no]['lwop_days'], $rows[$p->employee_no]['days_absent'], $rows[$p->employee_no]['deductions'], $rows[$p->employee_no]['monthly_basic']]);
        $this->assertSame(20, $rows[$cos->employee_no]['days_paid']);
        $this->assertSame(1200 * 20 - 300.0, $rows[$cos->employee_no]['monthly_basic']);
        $this->assertFalse($rows[$sp->employee_no]['paid_by_agency']);

        $json = $this->actingAs($this->hr)->get('/admin/payroll/export?month=2026-10&format=json')->assertOk()->json();
        $first = collect($json['employees'])->firstWhere('employee_no', $p->employee_no);
        $this->assertSame(['monthlyBasic' => 40000, 'frequency' => 'monthly', 'taxableAllowances' => 0, 'nonTaxable' => 0, 'otherDeductions' => 0], $first['computePay']);
        $this->assertSame(3600, $first['gsis']['employee']);
        $this->assertCount(2, $json['employees']); // service provider excluded
        $this->actingAs($this->hr)->get('/admin/payroll?month=2026-10')->assertOk()->assertSee('rdcruz-ph-payroll/payroll.js')->assertSee('Guard Co.');
        $this->assertStringContainsString('Monthly basic', $this->actingAs($this->hr)->get('/admin/payroll/export?month=2026-10&format=csv')->streamedContent());
    }

    public function test_ipcr_flow_and_rating(): void
    {
        Notification::fake();
        $boss = $this->person('permanent', [], login: true);
        $ana = $this->person('permanent', ['supervisor_id' => $boss->id], login: true);
        $this->actingAs($this->hr)->post('/admin/ipcr/periods', ['name' => 'July–December 2026', 'starts_on' => '2026-07-01', 'ends_on' => '2026-12-31']);
        $period = PerformancePeriod::sole();

        $this->actingAs($ana->user)->post("/me/ipcr/start/{$period->id}")->assertRedirect();
        $ipcr = Ipcr::sole();
        $this->assertSame($boss->id, $ipcr->rater_id);
        $this->actingAs($ana->user)->post("/me/ipcr/{$ipcr->id}", ['submit' => 1, 'items' => [
            ['category' => 'strategic', 'output' => 'Trade fair', 'success_indicator' => '500 buyers by Oct'],
            ['category' => 'core', 'output' => 'Reports', 'success_indicator' => '12 reports on time'],
            ['category' => 'support', 'output' => 'Trainings', 'success_indicator' => '2 trainings'],
        ]])->assertSessionHasNoErrors();
        $this->assertSame('targets_submitted', $ipcr->fresh()->status);

        $this->actingAs($boss->user)->post("/me/ipcr/{$ipcr->id}/review", ['decision' => 'approve'])->assertSessionHasNoErrors();
        $this->assertSame('targets_approved', $ipcr->fresh()->status);

        // Accomplishments only in the rating phase.
        $items = $ipcr->items()->get();
        $payload = ['submit' => 1, 'items' => $items->mapWithKeys(fn ($i) => [$i->id => ['accomplishment' => 'Done', 'self_q' => 5, 'self_e' => 5, 'self_t' => 4]])->all()];
        $this->actingAs($ana->user)->post("/me/ipcr/{$ipcr->id}", $payload)->assertStatus(422);
        $this->actingAs($this->hr)->put("/admin/ipcr/periods/{$period->id}", ['status' => 'rating']);
        $this->actingAs($ana->user)->post("/me/ipcr/{$ipcr->id}", $payload)->assertSessionHasNoErrors();
        $this->assertSame('self_rated', $ipcr->fresh()->status);

        // Strategic 5,5,5 = 5; core 4,4,4 = 4; support 3,3 (no T) = 3 → 5×.45 + 4×.45 + 3×.10 = 4.35 VS.
        [$s, $c, $u] = $items->all();
        $this->actingAs($boss->user)->post("/me/ipcr/{$ipcr->id}/review", ['comments' => 'Good work', 'items' => [
            $s->id => ['q' => 5, 'e' => 5, 't' => 5], $c->id => ['q' => 4, 'e' => 4, 't' => 4], $u->id => ['q' => 3, 'e' => 3],
        ]])->assertSessionHasNoErrors();
        $ipcr->refresh();
        $this->assertSame([4.35, 'Very Satisfactory', 'rated'], [$ipcr->final_rating, $ipcr->adjectival, $ipcr->status]);

        $this->actingAs($this->hr)->post("/admin/ipcr/{$ipcr->id}/finalize", ['decision' => 'finalize']);
        $this->assertSame('final', $ipcr->fresh()->status);
        $this->actingAs($this->hr)->get("/me/ipcr/{$ipcr->id}")->assertOk();
        $this->actingAs($this->hr)->get('/admin/ipcr')->assertOk()->assertSee('4.35');
        $this->actingAs($ana->user)->get("/print/ipcr/{$ipcr->id}")->assertOk()->assertSee('INDIVIDUAL PERFORMANCE COMMITMENT AND REVIEW')->assertSee('Very Satisfactory');
        $this->actingAs($ana->user)->get('/me/ipcr')->assertOk();
        $this->actingAs($boss->user)->get('/me/ipcr')->assertOk()->assertSee($ana->full_name);
    }

    public function test_pds_edit_sanitizes_and_prints(): void
    {
        $ana = $this->person('permanent', ['birth_date' => '1990-05-01'], login: true);
        $this->actingAs($ana->user)->put('/me/profile/pds', ['pds' => [
            'personal' => ['place_of_birth' => 'Manila', 'height' => '1.60', 'evil' => 'x'],
            'residential' => ['barangay' => 'San Lorenzo', 'city' => 'Makati'],
            'spouse' => ['surname' => 'Reyes', 'first' => 'Carlo'],
            'children' => [['name' => 'Mia Reyes', 'birth_date' => '2018-02-03'], ['name' => '', 'birth_date' => '']],
            'work_experience' => [['from' => '2012-06-01', 'to' => '2018-12-31', 'position' => 'Analyst', 'company' => 'ABC Corp.', 'government' => 'N']],
            'skills' => "Photography\nPublic speaking",
            'questions' => ['40c' => ['answer' => 'yes', 'details' => 'Solo parent ID 123'], '36' => ['answer' => 'maybe']],
            'references' => [['name' => 'Jose Cruz', 'address' => 'Pasig', 'telephone' => '0917']],
        ]])->assertRedirect('/me/profile');

        $pds = $ana->fresh()->pds;
        $this->assertArrayNotHasKey('evil', $pds['personal']);
        $this->assertCount(1, $pds['children']);
        $this->assertSame(['Photography', 'Public speaking'], $pds['skills']);
        $this->assertSame('', $pds['questions']['36']['answer']);

        $this->actingAs($ana->user)->get("/print/pds/{$ana->id}")->assertOk()
            ->assertSee('PERSONAL DATA SHEET')->assertSee('San Lorenzo')->assertSee('Mia Reyes')->assertSee('ABC Corp.')->assertSee('Solo parent ID 123');
        $this->actingAs($this->hr)->get("/admin/employees/{$ana->id}/pds")->assertOk()->assertSee('Mia Reyes');
        $other = $this->person('permanent', [], login: true);
        $this->actingAs($other->user)->get("/print/pds/{$ana->id}")->assertForbidden();
    }
}
