<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use App\Support\HelpTopics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class HelpPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_help_shows_topics_for_the_users_role(): void
    {
        $user = User::create(['name' => 'Ana', 'username' => 'ana', 'password' => 'secret123', 'role' => 'employee']);
        Employee::create(['employee_no' => 'E1', 'last_name' => 'Santos', 'first_name' => 'Ana', 'user_id' => $user->id]);
        $admin = User::create(['name' => 'Admin', 'username' => 'admin', 'password' => 'secret123', 'role' => 'admin']);

        $this->actingAs($user)->get('/help')->assertOk()->assertSee('Filing leave')->assertDontSee('Backups and restore')->assertSee('Show all topics');
        $this->actingAs($user)->get('/help?all=1')->assertOk()->assertSee('Backups and restore');
        $this->actingAs($admin)->get('/help')->assertOk()->assertSee('Backups and restore')->assertSee('Payroll, bonuses and payslips')->assertSee('Checking where a computer was registered');
        $this->get('/help')->assertOk();
        $this->post('/logout');
        $this->get('/help')->assertRedirect('/login');
    }

    public function test_every_screenshot_and_link_exists(): void
    {
        foreach (HelpTopics::all() as $t) {
            if (isset($t['image'])) {
                $this->assertFileExists(public_path('images/help/'.$t['image']), $t['id']);
            }
            if (isset($t['link'])) {
                $this->assertTrue(Route::has($t['link'][0]), $t['id']);
            }
        }
    }
}
