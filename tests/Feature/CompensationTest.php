<?php

namespace Tests\Feature;

use App\Models\Clearance;
use App\Models\Employee;
use App\Models\Ipcr;
use App\Models\IssuedDevice;
use App\Models\LeaveLedger;
use App\Models\LeaveMonetization;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\PerformancePeriod;
use App\Models\SalaryNotice;
use App\Models\SalarySchedule;
use App\Models\Setting;
use App\Models\User;
use App\Services\AnnualTax;
use App\Services\CompensationService;
use App\Services\LeaveService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class CompensationTest extends TestCase
{
    use RefreshDatabase;

    private User $hr;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-19 09:00:00');
        Setting::put(['session_idle_minutes' => '0', 'attendance_start_date' => '2026-12-01']);
        $this->hr = User::create(['name' => 'HR', 'username' => 'hr', 'password' => 'secret123', 'role' => 'admin']);
    }

    private function schedule(string $name = 'Tranche 2026', string $from = '2026-01-01', float $base = 30000): SalarySchedule
    {
        $s = SalarySchedule::create(['name' => $name, 'effective_date' => $from]);
        foreach ([15, 18] as $g) {
            for ($step = 1; $step <= 8; $step++) {
                $s->rates()->create(['grade' => $g, 'step' => $step, 'amount' => $base + $g * 1000 + $step * 500]);
            }
        }

        return $s;
    }

    private function officer(array $attrs = []): Employee
    {
        static $n = 0;
        $n++;

        return Employee::create(['employee_no' => "C$n", 'last_name' => "Reyes$n", 'first_name' => 'Ana', 'employment_type' => 'permanent', 'position' => 'Officer',
            'salary_grade' => 15, 'step' => 1, 'monthly_salary' => 45500, 'date_hired' => '2023-11-01', ...$attrs]);
    }

    public function test_step_increment_due_after_three_years_and_granted_with_nosi(): void
    {
        $this->schedule();
        $due = $this->officer();                                   // 2023-11-01 → due 2026-11-01
        $this->officer(['date_hired' => '2025-01-01']);             // not yet
        $held = $this->officer(['step_date' => '2023-10-01']);      // due, but Poor rating
        $period = PerformancePeriod::create(['name' => 'Jan–Jun 2025', 'starts_on' => '2025-01-01', 'ends_on' => '2025-06-30', 'status' => 'closed']);
        Ipcr::create(['employee_id' => $held->id, 'performance_period_id' => $period->id, 'status' => 'final', 'final_rating' => 1.2, 'adjectival' => 'Poor', 'finalized_at' => now()]);

        $list = app(CompensationService::class)->stepIncrements(60);
        $this->assertSame([$held->id, $due->id], $list->pluck('employee.id')->all());
        $this->assertStringContainsString('Poor', $list->firstWhere('employee.id', $held->id)['blocker']);
        $this->assertNull($list->firstWhere('employee.id', $due->id)['blocker']);
        $this->assertSame(46000.0, $list->firstWhere('employee.id', $due->id)['new_salary']);

        $this->actingAs($this->hr)->get('/admin/compensation')->assertOk()->assertSee('Grant');
        $this->post("/admin/compensation/grant/{$due->id}", ['effective_date' => '2026-11-01'])->assertSessionHasNoErrors();
        $due->refresh();
        $this->assertSame(2, $due->step);
        $this->assertSame('46000.00', $due->monthly_salary);
        $this->assertSame('2026-11-01', $due->step_date->toDateString());
        $notice = SalaryNotice::sole();
        $this->assertSame('nosi', $notice->type);
        $this->assertSame(552000.0, (float) $due->serviceRecords()->latest('date_from')->first()->annual_salary);
        $this->get("/admin/compensation/notices/{$notice->id}")->assertOk()->assertSee('NOTICE OF STEP INCREMENT')->assertSee('46,000.00')->assertSee('500.00');
    }

    public function test_salary_schedule_csv_upload_and_nosa(): void
    {
        $this->schedule();
        $e = $this->officer(['salary_grade' => 18, 'step' => 2, 'monthly_salary' => 49000]);
        $csv = "grade,step1,step2,step3,step4,step5,step6,step7,step8\n18,\"52,000\",53000,54000,55000,56000,57000,58000,59000\n15,47000,47500,48000,48500,49000,49500,50000,50500\n";
        $this->actingAs($this->hr)->post('/admin/compensation/schedules', ['name' => 'Tranche 2027', 'effective_date' => '2027-01-01',
            'file' => UploadedFile::fake()->createWithContent('t.csv', $csv)])->assertSessionHasNoErrors();
        $s = SalarySchedule::where('name', 'Tranche 2027')->sole();
        $this->assertSame(52000.0, $s->amount(18, 1));
        $this->assertSame(16, $s->rates()->count());

        $this->get("/admin/compensation/schedules/{$s->id}")->assertOk()->assertSee('1 employee(s) get a new salary');
        $this->post("/admin/compensation/schedules/{$s->id}/apply")->assertSessionHasNoErrors();
        $this->post("/admin/compensation/schedules/{$s->id}/apply"); // again: no duplicates
        $this->assertSame(1, SalaryNotice::where('type', 'nosa')->count());
        $this->assertSame('53000.00', $e->fresh()->monthly_salary);
        $this->assertNull($e->fresh()->step_date); // a salary adjustment doesn't restart the 3-year count
    }

    public function test_bonus_runs_and_annual_tax(): void
    {
        $e = $this->officer(['monthly_salary' => 52000, 'date_hired' => '2020-01-01']);
        $this->officer(['monthly_salary' => 40000, 'date_hired' => '2026-08-01']); // under 4 months by Oct 31
        Employee::create(['employee_no' => 'COS1', 'last_name' => 'Cos', 'first_name' => 'Jo', 'employment_type' => 'cos', 'daily_rate' => 1000]);

        $this->actingAs($this->hr)->post('/admin/payroll/runs', ['period' => '2026-11', 'cut' => 'year_end'])->assertSessionHasNoErrors();
        $run = PayrollRun::sole();
        $this->assertSame('Year-End Bonus & Cash Gift 2026', $run->label);
        $slip = Payslip::sole();
        $this->assertSame($e->id, $slip->employee_id);
        $this->assertSame(57000.0, $slip->gross); // 52,000 + 5,000 cash gift
        $this->assertSame(0.0, $slip->tax);
        $this->post('/admin/payroll/runs', ['period' => '2026-12', 'cut' => 'year_end'])->assertSessionHasErrors('period');

        // Regular October payroll + bonus, released → annual figures.
        $this->post('/admin/payroll/runs', ['period' => '2026-10', 'cut' => 'full']);
        PayrollRun::query()->update(['status' => 'released', 'released_at' => now()]);
        $t = app(AnnualTax::class)->forEmployee($e, 2026);
        $this->assertSame(57000.0, $t['benefits']);
        $this->assertSame(57000.0, $t['benefits_exempt']);
        $this->assertSame(45820.0, $t['taxable']);
        $this->assertSame(4372.4, $t['withheld']);
        $this->assertSame(0.0, $t['tax_due']); // ₱45,820 for the year is under ₱250,000
        $this->assertSame(-4372.4, $t['difference']);

        $this->get('/admin/payroll/tax?year=2026')->assertOk()->assertSee('Refund 4,372.40');
        $this->get("/admin/payroll/tax/certificate/{$e->id}?year=2026")->assertOk()->assertSee('CERTIFICATE OF COMPENSATION PAYMENT')->assertSee('45,820.00');
    }

    public function test_monetization_rules_and_terminal_leave(): void
    {
        $e = $this->officer(['monthly_salary' => 50000]);
        LeaveLedger::create(['employee_id' => $e->id, 'balance' => 'VL', 'entry_date' => '2026-01-01', 'particulars' => 'Balance', 'earned' => 20, 'source' => 'adjustment']);
        LeaveLedger::create(['employee_id' => $e->id, 'balance' => 'SL', 'entry_date' => '2026-01-01', 'particulars' => 'Balance', 'earned' => 30, 'source' => 'adjustment']);
        $e->serviceRecords()->create(['date_from' => '2020-01-01', 'date_to' => '2022-12-31', 'designation' => 'Officer', 'status' => 'Permanent', 'annual_salary' => 660000]);

        $this->actingAs($this->hr);
        $this->post('/admin/leave/monetization', ['employee_id' => $e->id, 'kind' => 'regular', 'date' => '2026-10-19', 'vl_days' => 8])->assertSessionHasErrors('vl_days');  // under 10
        $this->post('/admin/leave/monetization', ['employee_id' => $e->id, 'kind' => 'regular', 'date' => '2026-10-19', 'vl_days' => 16])->assertSessionHasErrors('vl_days'); // leaves < 5
        $this->post('/admin/leave/monetization', ['employee_id' => $e->id, 'kind' => 'special', 'date' => '2026-10-19', 'sl_days' => 10])->assertSessionHasErrors('reason');
        $this->post('/admin/leave/monetization', ['employee_id' => $e->id, 'kind' => 'regular', 'date' => '2026-10-19', 'vl_days' => 10])->assertSessionHasNoErrors();
        $this->assertSame(10.0, app(LeaveService::class)->balances($e)['VL']);
        $this->assertSame(24096.35, LeaveMonetization::sole()->amount); // 50,000 × 10 × 0.0481927

        // Terminal leave: 10 VL + 30 SL at the highest salary (₱55,000 from the service record).
        $this->get("/admin/leave/monetization?employee_id={$e->id}")->assertOk()->assertSee('₱106,023.94');
        $this->post('/admin/leave/monetization', ['employee_id' => $e->id, 'kind' => 'terminal', 'date' => '2026-10-31'])->assertSessionHasNoErrors();
        $this->assertSame(['VL' => 0.0, 'SL' => 0.0], array_intersect_key(app(LeaveService::class)->balances($e), ['VL' => 1, 'SL' => 1]));
    }

    public function test_clearance_separates_the_employee(): void
    {
        $u = User::create(['name' => 'Ana', 'username' => 'ana', 'password' => 'secret123', 'role' => 'employee']);
        $e = $this->officer(['user_id' => $u->id]);
        $e->serviceRecords()->create(['date_from' => '2023-11-01', 'designation' => 'Officer', 'status' => 'Permanent', 'annual_salary' => 546000]);
        IssuedDevice::create(['employee_id' => $e->id, 'name' => 'Dell Latitude']);

        $this->actingAs($this->hr)->post('/admin/clearances', ['employee_id' => $e->id, 'separation_date' => '2026-10-31', 'reason' => 'Resignation'])->assertSessionHasNoErrors();
        $c = Clearance::sole();
        $this->assertCount(count(config('hris.clearance_offices')), $c->items);
        $this->assertStringContainsString('Dell Latitude', $c->items->firstWhere('office', 'Property / Supply')->checks);

        $this->actingAs($u)->get('/me')->assertOk()->assertSee('Exit clearance in progress');
        $this->actingAs($this->hr)->post("/admin/clearances/{$c->id}/complete")->assertSessionHasErrors('clearance');
        foreach ($c->items as $i) {
            $this->post("/admin/clearances/items/{$i->id}/sign", ['cleared_by' => 'Office head'])->assertSessionHasNoErrors();
        }
        $this->post("/admin/clearances/{$c->id}/complete")->assertSessionHasNoErrors();

        $e->refresh();
        $this->assertFalse($e->is_active);
        $this->assertSame('2026-10-31', $e->date_separated->toDateString());
        $this->assertNotNull($u->fresh()->disabled_at);
        $this->assertFalse(IssuedDevice::sole()->is_active);
        $this->assertSame('2026-10-31', $e->serviceRecords()->sole()->date_to->toDateString());
        $this->get("/print/clearance/{$c->id}")->assertOk()->assertSee('CLEARANCE CERTIFICATE')->assertSee('Verify this document');
    }

    public function test_loyalty_rai_plantilla_and_saln(): void
    {
        $ten = $this->officer(['date_hired' => '2016-03-01']);
        $this->officer(['date_hired' => '2011-05-01']);  // 15 years
        $this->officer(['date_hired' => '2014-05-01']);  // 12 years: no milestone

        $this->actingAs($this->hr)->get('/admin/reports/loyalty?year=2026')->assertOk()->assertSee('REYES', false)->assertSee('₱10,000.00')->assertSee('₱5,000.00');
        $this->post("/admin/reports/loyalty/{$ten->id}", ['years' => 10, 'amount' => 10000])->assertSessionHasNoErrors();
        $this->get('/admin/reports/loyalty?year=2026')->assertSee('Awarded');

        $ten->serviceRecords()->create(['date_from' => '2026-10-01', 'designation' => 'Senior Officer', 'status' => 'Permanent', 'annual_salary' => 600000, 'remarks' => 'Promotion']);
        $this->get('/admin/reports/rai?month=2026-10')->assertOk()->assertSee('Senior Officer')->assertSee('Promotion');
        $this->get('/admin/reports/plantilla?print=1')->assertOk()->assertSee('PLANTILLA OF PERSONNEL');

        $this->get('/admin/reports/saln?year=2025')->assertOk()->assertSee('3 not yet filed', false);
        $this->post("/admin/reports/saln/{$ten->id}", ['year' => 2025, 'filed_on' => '2026-04-20', 'filing' => 'joint'])->assertSessionHasNoErrors();
        $this->get('/admin/reports/saln?year=2025')->assertSee('2 not yet filed', false);
    }
}
