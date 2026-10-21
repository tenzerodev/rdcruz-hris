<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private function person(string $username, array $attrs = []): Employee
    {
        $user = User::create(['name' => ucfirst($username), 'username' => $username, 'password' => 'secret123', 'role' => 'employee']);

        return Employee::create(['employee_no' => strtoupper($username), 'last_name' => ucfirst($username), 'first_name' => 'X', 'employment_type' => 'permanent', 'user_id' => $user->id, ...$attrs]);
    }

    public function test_notices_show_in_the_bell_and_open_their_page(): void
    {
        Carbon::setTestNow('2026-11-04 09:00:00');
        Setting::put(['session_idle_minutes' => '0', 'notifications_enabled' => '0']);
        $boss = $this->person('boss');
        $ana = $this->person('ana', ['supervisor_id' => $boss->id]);

        $this->actingAs($ana->user)->post('/me/pass-slips', ['date' => '2026-11-04', 'type' => 'official', 'time_out' => '14:00',
            'destination' => 'DBM', 'purpose' => 'Budget hearing'])->assertSessionHasNoErrors();

        // Email is off, but the supervisor still sees it in the HRIS.
        $notice = $boss->user->unreadNotifications()->sole();
        $this->assertStringContainsString('Pass slip', $notice->data['subject']);
        $this->actingAs($boss->user)->get('/me')->assertOk()->assertSee('Notifications')->assertSee('class="count"', false);
        $this->get('/notifications')->assertOk()->assertSee('Budget hearing');
        $this->get("/notifications/{$notice->id}")->assertRedirect(route('me.approvals'));
        $this->assertNotNull($notice->fresh()->read_at);

        // Someone else's notice can't be opened.
        $this->actingAs($ana->user)->get("/notifications/{$notice->id}")->assertNotFound();

        $this->post('/notifications/preferences', [])->assertSessionHas('status');
        $this->assertFalse($ana->user->fresh()->notify_email);
    }

    public function test_daily_reminders(): void
    {
        Carbon::setTestNow('2026-04-06 07:30:00'); // a Monday in April
        Setting::put(['notifications_enabled' => '0']);
        $boss = $this->person('boss');
        $ana = $this->person('ana', ['supervisor_id' => $boss->id]);
        $leave = LeaveApplication::create(['employee_id' => $ana->id, 'type' => 'VL', 'date_from' => '2026-04-20', 'date_to' => '2026-04-20', 'days' => 1, 'status' => 'pending']);
        $leave->forceFill(['created_at' => now()->subDays(3)])->save();

        $this->artisan('hris:reminders')->assertSuccessful();
        $this->assertSame(1, $boss->user->notifications()->where('data->subject', 'like', '%waiting for your approval%')->count());
        $this->assertTrue($ana->user->notifications()->get()->contains(fn ($n) => str_contains($n->data['subject'], '2025 SALN')));

        // Running again the same day sends nothing new.
        $this->artisan('hris:reminders')->assertSuccessful();
        $this->assertSame(1, $boss->user->notifications()->where('data->subject', 'like', '%waiting for your approval%')->count());
    }
}
