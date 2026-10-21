<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Setting;
use App\Models\TrainingNeed;
use App\Models\TrainingProgram;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LearningTest extends TestCase
{
    use RefreshDatabase;

    public function test_needs_to_plan_to_attendance_on_the_201_file(): void
    {
        Carbon::setTestNow('2026-11-05 09:00:00');
        Setting::put(['session_idle_minutes' => '0', 'notifications_enabled' => '0']);
        $hr = User::create(['name' => 'HR', 'username' => 'hr', 'password' => 'secret123', 'role' => 'hr']);
        $user = User::create(['name' => 'Ana', 'username' => 'ana', 'password' => 'secret123', 'role' => 'employee']);
        $ana = Employee::create(['employee_no' => 'E1', 'last_name' => 'Santos', 'first_name' => 'Ana', 'user_id' => $user->id]);
        $ben = Employee::create(['employee_no' => 'E2', 'last_name' => 'Cruz', 'first_name' => 'Ben']);

        $this->actingAs($user)->post('/me/training/needs', ['year' => 2027, 'competency' => 'Data analysis', 'training_title' => 'Advanced Excel', 'priority' => 'high'])->assertSessionHasNoErrors();
        $need = TrainingNeed::sole();

        $this->actingAs($hr)->get('/admin/learning?year=2027&tab=needs')->assertOk()->assertSee('Advanced Excel')->assertSee('SANTOS');
        $this->post('/admin/learning', ['year' => 2027, 'title' => 'Advanced Excel', 'competency' => 'Data analysis', 'type' => 'technical', 'mode' => 'in_house',
            'quarter' => 1, 'hours' => 16, 'budget' => 30000, 'need_ids' => [$need->id]])->assertSessionHasNoErrors();
        $program = TrainingProgram::sole();
        $this->assertSame($program->id, $need->fresh()->training_program_id);

        $this->get("/admin/learning/{$program->id}")->assertOk()->assertSee('Asked for this in the needs survey');
        $this->post("/admin/learning/{$program->id}/nominate", ['employee_ids' => [$ana->id, $ben->id]])->assertSessionHasNoErrors();
        $this->assertSame(1, $user->notifications()->count());
        $this->actingAs($user)->get('/me/training')->assertOk()->assertSee('You are nominated for')->assertSee('Advanced Excel');

        // Ana attended, Ben didn't: only Ana gets it on her 201 file.
        $anaP = $program->participants()->where('employee_id', $ana->id)->sole();
        $this->actingAs($hr)->post("/admin/learning/{$program->id}/complete", ['attended' => [$anaP->id], 'date_from' => '2026-11-03', 'hours' => 16, 'actual_cost' => 28000])->assertSessionHasNoErrors();
        $this->assertSame('done', $program->fresh()->status);
        $this->assertSame(['Advanced Excel'], $ana->trainings()->pluck('title')->all());
        $this->assertSame(0, $ben->trainings()->count());
        $this->get('/admin/learning?year=2026&tab=hours')->assertOk()->assertSee('16');
        $this->get('/admin/learning/plan?year=2027')->assertOk()->assertSee('LEARNING AND DEVELOPMENT PLAN 2027');
        $this->delete("/admin/learning/{$program->id}")->assertStatus(422);
    }
}
