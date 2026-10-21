<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Position;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_analytics_page_shows_workforce_figures(): void
    {
        Carbon::setTestNow('2026-11-05 09:00:00');
        Setting::put(['session_idle_minutes' => '0']);
        $hr = User::create(['name' => 'HR', 'username' => 'hr', 'password' => 'secret123', 'role' => 'hr']);
        $p1 = Position::create(['item_no' => 'A1', 'title' => 'AO I', 'is_active' => true]);
        Position::create(['item_no' => 'A2', 'title' => 'AO II', 'is_active' => true]);
        Employee::create(['employee_no' => 'E1', 'last_name' => 'Old', 'first_name' => 'A', 'employment_type' => 'permanent', 'position_id' => $p1->id,
            'office' => 'FAD', 'sex' => 'female', 'birth_date' => '1962-03-01', 'date_hired' => '2026-02-01']);
        Employee::create(['employee_no' => 'E2', 'last_name' => 'Left', 'first_name' => 'B', 'employment_type' => 'cos', 'is_active' => false,
            'date_hired' => '2020-01-01', 'date_separated' => '2026-06-30', 'separation_reason' => 'Resignation']);

        $this->actingAs($hr)->get('/admin/reports/analytics?year=2026')->assertOk()
            ->assertSee('50%')        // 1 of 2 items vacant
            ->assertSee('Resignation')
            ->assertSee('OLD, A', false)
            ->assertSee('turns 65 on Mar 1, 2027');
    }
}
