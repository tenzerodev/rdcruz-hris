<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Ipcr;
use App\Models\OfficeRating;
use App\Models\PerformancePeriod;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfficeRatingTest extends TestCase
{
    use RefreshDatabase;

    public function test_head_sets_targets_hr_rates_and_ipcrs_are_compared(): void
    {
        Setting::put(['session_idle_minutes' => '0', 'notifications_enabled' => '0']);
        $hr = User::create(['name' => 'HR', 'username' => 'hr', 'password' => 'secret123', 'role' => 'hr']);
        $headUser = User::create(['name' => 'Head', 'username' => 'head', 'password' => 'secret123', 'role' => 'employee']);
        $head = Employee::create(['employee_no' => 'H1', 'last_name' => 'Head', 'first_name' => 'A', 'office' => 'FAD', 'user_id' => $headUser->id]);
        $staff = Employee::create(['employee_no' => 'S1', 'last_name' => 'Staff', 'first_name' => 'B', 'office' => 'FAD']);
        $period = PerformancePeriod::create(['name' => 'Jan–Jun 2026', 'starts_on' => '2026-01-01', 'ends_on' => '2026-06-30', 'status' => 'planning']);

        $this->actingAs($hr)->post('/admin/office-ratings', ['performance_period_id' => $period->id, 'level' => 'opcr', 'office' => 'FAD', 'head_id' => $head->id])->assertRedirect();
        $rating = OfficeRating::sole();
        $this->post('/admin/office-ratings', ['performance_period_id' => $period->id, 'level' => 'opcr', 'office' => 'FAD'])->assertSessionHasErrors('office');

        // The head enters targets.
        $this->actingAs($headUser)->get('/me/ipcr')->assertOk()->assertSee('Office / division ratings I head');
        $this->post("/performance/offices/{$rating->id}", ['items' => [
            ['category' => 'core', 'output' => 'Payroll', 'success_indicator' => '24 payrolls on time', 'budget' => 50000, 'accountable' => 'Accounting'],
            ['category' => 'support', 'output' => 'Training', 'success_indicator' => '2 trainings'],
        ]])->assertSessionHasNoErrors();
        $this->assertSame(2, $rating->items()->count());
        $this->post("/performance/offices/{$rating->id}/decide", ['decision' => 'approve'])->assertForbidden(); // only HR approves

        $this->actingAs($hr)->post("/performance/offices/{$rating->id}/decide", ['decision' => 'approve'])->assertSessionHasNoErrors();
        $period->update(['status' => 'rating']);
        [$a, $b] = $rating->items()->get()->all();
        $this->post("/performance/offices/{$rating->id}", ['submit' => 1, 'items' => [
            $a->id => ['accomplishment' => '24 on time', 'q' => 4, 'e' => 4, 't' => 4],
            $b->id => ['accomplishment' => '2 done', 'q' => 3, 'e' => 3, 't' => 3],
        ]])->assertSessionHasNoErrors();
        $rating->refresh();
        $this->assertSame('rated', $rating->status);
        $this->assertSame(3.82, $rating->final_rating); // (4 × .45 + 3 × .10) / .55
        $this->post("/performance/offices/{$rating->id}/decide", ['decision' => 'finalize']);

        // An IPCR in that office rated higher than the office is flagged.
        Ipcr::create(['employee_id' => $staff->id, 'performance_period_id' => $period->id, 'status' => 'final', 'final_rating' => 4.6, 'adjectival' => 'Outstanding']);
        $this->get("/admin/office-ratings?period={$period->id}")->assertOk()->assertSee('Above office rating');
        $this->get("/performance/offices/{$rating->id}/print")->assertOk()->assertSee('OFFICE PERFORMANCE COMMITMENT AND REVIEW');

        // Someone else can't open it.
        $other = User::create(['name' => 'O', 'username' => 'o', 'password' => 'secret123', 'role' => 'employee']);
        Employee::create(['employee_no' => 'O1', 'last_name' => 'O', 'first_name' => 'O', 'user_id' => $other->id]);
        $this->actingAs($other)->get("/performance/offices/{$rating->id}")->assertForbidden();
    }
}
