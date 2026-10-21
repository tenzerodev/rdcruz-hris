<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Models\Setting;
use App\Models\User;
use App\Services\LeaveService;
use App\Services\PayrollInputs;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class WellnessLeaveTest extends TestCase
{
    use RefreshDatabase;

    private User $hr;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-19 09:00:00');
        Setting::put(['session_idle_minutes' => '0']);
        $this->hr = User::create(['name' => 'HR', 'username' => 'hr', 'password' => 'secret123', 'role' => 'hr']);
    }

    private function person(string $type, ?string $hired, bool $login = false): Employee
    {
        static $n = 0;
        $n++;
        $e = Employee::create(['employee_no' => "W$n", 'last_name' => "Cruz$n", 'first_name' => 'Juan', 'employment_type' => $type, 'date_hired' => $hired, 'daily_rate' => 1000]);
        if ($login) {
            $u = User::create(['name' => 'Juan', 'username' => "w$n", 'password' => 'secret123', 'role' => 'employee']);
            $e->update(['user_id' => $u->id]);
        }

        return $e->fresh();
    }

    public function test_entitlement_by_start_date_and_employment_type(): void
    {
        $leaves = app(LeaveService::class);
        $this->assertSame(5.0, $leaves->wellnessEntitlement($this->person('cos', '2025-08-01'), 2026));            // started last year
        $this->assertSame(2.0, $leaves->wellnessEntitlement($this->person('cos', '2026-01-02'), 2026));            // January
        $this->assertSame(2.0, $leaves->wellnessEntitlement($this->person('service_provider', '2026-06-30'), 2026)); // June
        $this->assertSame(0.0, $leaves->wellnessEntitlement($this->person('cos', '2026-07-01'), 2026));            // July onwards
        $this->assertSame(5.0, $leaves->wellnessEntitlement($this->person('cos', '2026-07-01'), 2027));            // full the next year
        $this->assertSame(0.0, $leaves->wellnessEntitlement($this->person('job_order', '2025-01-01'), 2026));
        $this->assertSame(0.0, $leaves->wellnessEntitlement($this->person('permanent', '2020-01-01'), 2026));
    }

    public function test_filing_is_limited_to_the_remaining_days(): void
    {
        $e = $this->person('cos', '2026-03-01', login: true); // 2 days this year
        $this->actingAs($e->user)->get('/me/leave')->assertOk()->assertSee('Wellness leave left')->assertSee('Wellness Leave');

        $this->post('/me/leave', ['type' => 'WL', 'date_from' => '2026-10-26', 'date_to' => '2026-10-28'])->assertSessionHasErrors('type'); // 3 days > 2
        $this->post('/me/leave', ['type' => 'WL', 'date_from' => '2026-10-26', 'date_to' => '2026-10-27'])->assertSessionHasNoErrors();
        $this->post('/me/leave', ['type' => 'WL', 'date_from' => '2026-11-02', 'date_to' => '2026-11-02'])->assertSessionHasErrors('type'); // none left

        $plantilla = $this->person('permanent', '2020-01-01');
        $this->expectException(ValidationException::class);
        app(LeaveService::class)->file($plantilla, ['type' => 'WL', 'date_from' => '2026-10-26', 'date_to' => '2026-10-26'], $this->hr);
    }

    public function test_approved_wellness_leave_is_paid_for_contract_of_service(): void
    {
        $e = $this->person('cos', '2024-01-15');
        $leaves = app(LeaveService::class);
        $app = $leaves->file($e, ['type' => 'WL', 'date_from' => '2026-09-14', 'date_to' => '2026-09-15'], $this->hr);
        $leaves->approve($app, $this->hr);
        $this->assertSame(2.0, (float) LeaveApplication::find($app->id)->days_with_pay);
        $this->assertSame(['entitled' => 5.0, 'used' => 2.0, 'remaining' => 3.0], $leaves->wellness($e, 2026));

        $row = collect(app(PayrollInputs::class)->rows(2026, 9))->firstWhere('employee_no', $e->employee_no);
        $this->assertSame(2.0, $row['wellness_days']);
        $this->assertSame(2000.0, $row['monthly_basic']); // 2 paid wellness days × ₱1,000, no other attendance
    }
}
