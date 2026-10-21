<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Setting;
use App\Models\User;
use App\Support\TwoFactor;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-26 09:00:00');
        $this->admin = User::create(['name' => 'Admin', 'username' => 'admin', 'password' => 'secret123', 'role' => 'admin']);
    }

    private function staff(string $role, bool $employee = false): User
    {
        $user = User::create(['name' => ucfirst($role), 'username' => $role, 'password' => 'secret123', 'role' => $role]);
        if ($employee) {
            Employee::create(['employee_no' => 'S-'.$role, 'last_name' => ucfirst($role), 'first_name' => 'Staff', 'user_id' => $user->id]);
        }

        return $user;
    }

    public function test_roles_limit_what_staff_can_see_and_change(): void
    {
        $e = Employee::create(['employee_no' => 'E1', 'last_name' => 'Cruz', 'first_name' => 'Juan']);
        $auditor = $this->staff('auditor');
        $payroll = $this->staff('payroll');
        $timekeeper = $this->staff('timekeeper');
        $hr = $this->staff('hr');

        // Auditor: reads everything HR, changes nothing, no system pages.
        foreach (['/admin', '/admin/employees', "/admin/employees/{$e->id}", '/admin/punches', '/admin/payroll', '/admin/audit'] as $url) {
            $this->actingAs($auditor)->get($url)->assertOk();
        }
        $this->actingAs($auditor)->get('/admin/employees')->assertSee('Read-only');
        $this->actingAs($auditor)->post('/admin/holidays', ['date' => '2026-12-24', 'name' => 'Christmas Eve'])->assertForbidden();
        $this->actingAs($auditor)->delete("/admin/employees/{$e->id}")->assertForbidden();
        $this->actingAs($auditor)->get('/admin/users')->assertForbidden();
        $this->actingAs($auditor)->get('/admin/backups')->assertForbidden();

        // Payroll: views attendance, cannot change it; cannot open HR settings.
        $this->actingAs($payroll)->get('/admin/punches')->assertOk();
        $this->actingAs($payroll)->post('/admin/punches', [])->assertForbidden();
        $this->actingAs($payroll)->get('/admin/settings')->assertForbidden();

        // Timekeeper: manages attendance but not payroll; the menu hides what they can't open.
        $this->actingAs($timekeeper)->get('/admin/payroll')->assertForbidden();
        $this->actingAs($timekeeper)->get('/admin')->assertOk()->assertSee('Attendance log')->assertDontSee('Payroll inputs')->assertDontSee('User accounts');

        // HR: everything HR, but not user accounts or backups.
        $this->actingAs($hr)->get('/admin/settings')->assertOk();
        $this->actingAs($hr)->get('/admin/users')->assertForbidden();
        $this->actingAs($this->admin)->get('/admin/users')->assertOk();
    }

    public function test_staff_linked_to_an_employee_record_also_use_the_employee_pages(): void
    {
        $hr = $this->staff('hr', employee: true);
        $this->actingAs($hr)->get('/me')->assertOk()->assertSee('Back to HR admin');
        $this->actingAs($this->admin)->get('/me')->assertForbidden(); // no employee record

        // Editing the HR officer's employee login keeps their role.
        $emp = $hr->employee;
        $this->actingAs($this->admin)->put("/admin/employees/{$emp->id}", [
            'employee_no' => $emp->employee_no, 'last_name' => 'Hr', 'first_name' => 'Staff', 'employment_type' => 'permanent',
            'schedule_type' => 'flexi', 'is_active' => 1, 'username' => 'hr',
        ])->assertSessionHasNoErrors();
        $this->assertSame('hr', $hr->fresh()->role);
    }

    public function test_account_locks_after_repeated_wrong_passwords(): void
    {
        Setting::put(['lockout_attempts' => '3']);
        for ($i = 0; $i < 3; $i++) {
            $this->post('/login', ['username' => 'admin', 'password' => 'wrong'])->assertSessionHasErrors(['username' => 'Wrong username or password.']);
        }
        $this->post('/login', ['username' => 'admin', 'password' => 'secret123'])->assertSessionHasErrors('username');
        $this->assertGuest();
        $this->assertSame(3, DB::table('audit_logs')->where('action', 'auth.failed')->count());

        Carbon::setTestNow(now()->addMinutes(16));
        $this->post('/login', ['username' => 'admin', 'password' => 'secret123'])->assertRedirect('/admin');
    }

    public function test_totp_matches_rfc_6238(): void
    {
        $secret = TwoFactor::base32Encode('12345678901234567890');
        $this->assertSame('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', $secret);
        $this->assertSame('287082', TwoFactor::code($secret, 1));          // T = 59 s
        $this->assertSame('081804', TwoFactor::code($secret, 37037036));   // T = 1111111109 s
    }

    public function test_two_step_verification_setup_and_sign_in(): void
    {
        $this->actingAs($this->admin)->post('/account/two-factor', ['password' => 'secret123'])->assertRedirect('/account/security');
        $secret = session('2fa.pending');
        $this->get('/account/security')->assertOk()->assertSee('scan this code');
        $this->post('/account/two-factor/confirm', ['code' => '000000'])->assertSessionHasErrors('code');
        $step = intdiv(now()->getTimestamp(), 30);
        $this->post('/account/two-factor/confirm', ['code' => TwoFactor::code($secret, $step)])->assertSessionHasNoErrors();
        $admin = $this->admin->fresh();
        $this->assertTrue($admin->hasTwoFactor());
        $codes = $admin->two_factor_recovery_codes;
        $this->assertCount(8, $codes);
        $this->assertNotSame($secret, DB::table('users')->where('id', $admin->id)->value('two_factor_secret')); // stored encrypted

        $this->post('/logout');
        $this->post('/login', ['username' => 'admin', 'password' => 'secret123'])->assertRedirect('/login/two-factor');
        $this->assertGuest();
        $this->post('/login/two-factor', ['code' => '123456'])->assertSessionHasErrors('code');
        Carbon::setTestNow(now()->addSeconds(30));
        $this->post('/login/two-factor', ['code' => TwoFactor::code($secret, $step + 1)])->assertRedirect('/admin');
        $this->assertAuthenticatedAs($admin);

        // A recovery code works once.
        $this->post('/logout');
        $this->post('/login', ['username' => 'admin', 'password' => 'secret123']);
        $this->post('/login/two-factor', ['recovery_code' => $codes[0]])->assertRedirect('/admin');
        $this->post('/logout');
        $this->post('/login', ['username' => 'admin', 'password' => 'secret123']);
        $this->post('/login/two-factor', ['recovery_code' => $codes[0]])->assertSessionHasErrors('code');
        $this->assertCount(7, $admin->fresh()->two_factor_recovery_codes);
    }

    public function test_required_two_step_and_forced_password_change_send_users_to_security_page(): void
    {
        $hr = $this->staff('hr');
        Setting::put(['require_2fa_staff' => '1']);
        $this->actingAs($hr)->get('/admin')->assertRedirect('/account/security');
        $this->actingAs($hr)->get('/account/security')->assertOk()->assertSee('Required for your account');

        Setting::put(['require_2fa_staff' => '0']);
        $hr->forceFill(['must_change_password' => true])->save();
        $this->actingAs($hr)->get('/admin')->assertRedirect('/account/security');
        $this->put('/account/password', ['current_password' => 'secret123', 'password' => 'short1', 'password_confirmation' => 'short1'])->assertSessionHasErrors('password');
        $this->put('/account/password', ['current_password' => 'secret123', 'password' => 'a longer pass 99', 'password_confirmation' => 'a longer pass 99'])->assertSessionHasNoErrors();
        $this->actingAs($hr->fresh())->get('/admin')->assertOk();
    }

    public function test_idle_sessions_are_signed_out_and_disabled_accounts_cannot_sign_in(): void
    {
        Setting::put(['session_idle_minutes' => '30']);
        $this->actingAs($this->admin)->get('/admin')->assertOk();
        Carbon::setTestNow(now()->addMinutes(31));
        $this->get('/admin')->assertRedirect('/login');
        $this->assertGuest();

        $hr = $this->staff('hr');
        $this->actingAs($this->admin)->post("/admin/users/{$hr->id}/toggle")->assertSessionHasNoErrors();
        $this->post('/logout');
        $this->post('/login', ['username' => 'hr', 'password' => 'secret123'])->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_admin_manages_accounts_and_cannot_remove_the_last_administrator(): void
    {
        $e = Employee::create(['employee_no' => 'E9', 'last_name' => 'Reyes', 'first_name' => 'Maria']);
        $this->actingAs($this->admin)->post('/admin/users', ['name' => 'Maria Reyes', 'username' => 'mreyes', 'role' => 'payroll', 'employee_id' => $e->id])
            ->assertSessionHas('status');
        $user = User::where('username', 'mreyes')->sole();
        $this->assertTrue($user->must_change_password);
        $this->assertSame($user->id, $e->fresh()->user_id);

        $this->actingAs($this->admin)->put("/admin/users/{$this->admin->id}", ['name' => 'Admin', 'role' => 'hr'])->assertSessionHasErrors('role');
        $this->assertSame('admin', $this->admin->fresh()->role);
        $this->actingAs($this->admin)->post("/admin/users/{$user->id}/reset-password")->assertSessionHas('status');
    }

    public function test_setup_wizard_runs_once_on_a_fresh_install(): void
    {
        $this->admin->delete();
        $this->get('/login')->assertRedirect('/setup');
        $this->get('/setup')->assertOk()->assertSee('set up your HRIS');
        $this->post('/setup', ['agency_name' => 'Test Agency', 'name' => 'IT Officer', 'username' => 'itadmin',
            'password' => 'weak', 'password_confirmation' => 'weak'])->assertSessionHasErrors('password');
        $this->post('/setup', ['agency_name' => 'Test Agency', 'name' => 'IT Officer', 'username' => 'itadmin', 'holidays' => 1,
            'password' => 'strong pass 2026', 'password_confirmation' => 'strong pass 2026'])->assertRedirect(route('admin.system.index'));

        $this->assertAuthenticated();
        $this->assertSame('admin', User::where('username', 'itadmin')->value('role'));
        $this->assertSame('Test Agency', Setting::get('agency_name'));
        $this->assertDatabaseHas('holidays', ['name' => 'Bonifacio Day']);
        $this->get('/setup')->assertNotFound();
    }

    public function test_government_ids_are_encrypted_at_rest(): void
    {
        $e = Employee::create(['employee_no' => 'E2', 'last_name' => 'Cruz', 'first_name' => 'Juan', 'tin' => '123-456-789-000']);
        $raw = DB::table('employees')->where('id', $e->id)->value('tin');
        $this->assertNotSame('123-456-789-000', $raw);
        $this->assertSame('123-456-789-000', $e->fresh()->tin);
    }
}
