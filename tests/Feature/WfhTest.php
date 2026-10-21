<?php

namespace Tests\Feature;

use App\Imports\WfhImporter;
use App\Models\Employee;
use App\Models\IssuedDevice;
use App\Models\Setting;
use App\Models\User;
use App\Models\WfhArrangement;
use App\Models\WfhReport;
use App\Support\DeviceCookie;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WfhTest extends TestCase
{
    use RefreshDatabase;

    private User $hr;

    private User $user;

    private Employee $juan;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-19 08:00:00'); // Monday
        Setting::put(['session_idle_minutes' => '0']); // tests jump days ahead
        $this->hr = User::create(['name' => 'HR', 'username' => 'hr', 'password' => 'secret123', 'role' => 'hr']);
        $this->user = User::create(['name' => 'Juan', 'username' => 'juan', 'password' => 'secret123', 'role' => 'employee']);
        // Kiosk-only employee: personal-device punching is off.
        $this->juan = Employee::create(['employee_no' => 'E1', 'last_name' => 'Cruz', 'first_name' => 'Juan', 'consent_at' => now(),
            'user_id' => $this->user->id, 'allow_personal' => false, 'email' => 'juan@agency.gov.ph']);
        $this->juan->faceSamples()->create(['descriptor' => $this->face()]);
    }

    private function face(): array
    {
        mt_srand(1);

        return array_map(fn () => (mt_rand(-1000, 1000) / 1000) * 0.15, range(1, 128));
    }

    /** Registers a computer for Juan the way HR and Juan would, and returns the browser's device cookie. */
    private function registeredComputer(): array
    {
        $this->actingAs($this->hr)->post('/admin/devices', ['employee_id' => $this->juan->id, 'name' => 'Dell Latitude 5440', 'property_no' => 'PAR-0912'])
            ->assertSessionHas('code');
        $code = session('code')['code'];

        $response = $this->actingAs($this->user)->post('/me/devices/register', ['code' => strtolower($code)])->assertSessionHasNoErrors();

        return [DeviceCookie::NAME => $response->getCookie(DeviceCookie::NAME)->getValue()];
    }

    public function test_hr_sets_rotation_and_employee_punches_from_issued_computer_only_on_wfh_days(): void
    {
        $this->actingAs($this->hr)->post('/admin/wfh', ['employee_ids' => [$this->juan->id], 'date_from' => '2026-10-19', 'date_to' => '2026-10-30',
            'weekdays' => [1, 3], 'require_device' => 1, 'reference_no' => 'OO-2026-120'])->assertSessionHasNoErrors();
        $a = WfhArrangement::sole();
        $this->assertSame('Mon, Wed', $a->days_label);
        $this->assertTrue($a->covers(Carbon::parse('2026-10-21')));
        $this->assertFalse($a->covers(Carbon::parse('2026-10-20')));

        // Monday, WFH: without the registered computer the punch is refused.
        $this->actingAs($this->user)->postJson('/me/punch', ['descriptor' => $this->face()])
            ->assertForbidden()->assertJsonPath('message', fn ($m) => str_contains($m, 'agency-issued computer'));

        // From the registered computer it works, and the office geofence doesn't apply at home.
        $cookie = $this->registeredComputer();
        Setting::put(['geofence_enabled' => '1', 'geofence_lat' => '14.5547', 'geofence_lng' => '121.0244', 'geofence_radius' => '150']);
        $this->actingAs($this->user)->withCredentials()->withCookies($cookie)
            ->postJson('/me/punch', ['descriptor' => $this->face(), 'latitude' => 14.70, 'longitude' => 121.10])
            ->assertOk()->assertJsonPath('punch.type', 'am_in');
        $punch = $this->juan->punches()->sole();
        $this->assertSame('wfh', $punch->source);
        $this->assertSame('Dell Latitude 5440', $punch->device->name);
        $this->assertNotNull($punch->device->last_used_at);

        // Tuesday is an office day: kiosk only, even from the issued computer.
        Carbon::setTestNow('2026-10-20 08:00:00');
        $this->actingAs($this->user)->withCredentials()->withCookies($cookie)->postJson('/me/punch', ['descriptor' => $this->face()])
            ->assertForbidden()->assertJsonPath('message', 'You are only allowed to punch at an office kiosk.');
    }

    public function test_activation_codes_are_single_use_personal_and_devices_can_be_disabled(): void
    {
        $other = User::create(['name' => 'Ana', 'username' => 'ana', 'password' => 'secret123', 'role' => 'employee']);
        Employee::create(['employee_no' => 'E2', 'last_name' => 'Santos', 'first_name' => 'Ana', 'user_id' => $other->id]);

        $this->actingAs($this->hr)->post('/admin/devices', ['employee_id' => $this->juan->id, 'name' => 'HP ProBook']);
        $code = session('code')['code'];
        $this->actingAs($other)->post('/me/devices/register', ['code' => $code])->assertSessionHasErrors('code'); // not hers
        $this->actingAs($this->user)->post('/me/devices/register', ['code' => $code])->assertSessionHasNoErrors();
        $this->actingAs($this->user)->post('/me/devices/register', ['code' => $code])->assertSessionHasErrors('code'); // used

        $device = IssuedDevice::sole();
        $this->assertSame('active', $device->status);
        $this->actingAs($this->hr)->post("/admin/devices/{$device->id}/toggle");
        $this->assertSame('disabled', $device->fresh()->status);

        // Codes expire after 3 days.
        $device->forceFill(['is_active' => true])->save();
        $fresh = $device->newActivationCode();
        Carbon::setTestNow(now()->addDays(4));
        $this->actingAs($this->user)->post('/me/devices/register', ['code' => $fresh])->assertSessionHasErrors('code');
    }

    public function test_devices_page_shows_the_browser_and_ip_where_each_code_was_entered(): void
    {
        $windows = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36';
        $iphone = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1';

        $this->actingAs($this->hr)->post('/admin/devices', ['employee_id' => $this->juan->id, 'name' => 'Dell Latitude 5440']);
        $this->actingAs($this->user)->withHeader('User-Agent', $windows)->withServerVariables(['REMOTE_ADDR' => '203.177.10.5'])
            ->post('/me/devices/register', ['code' => session('code')['code']])->assertSessionHasNoErrors();

        $this->actingAs($this->hr)->post('/admin/devices', ['employee_id' => $this->juan->id, 'name' => 'Lenovo ThinkPad']);
        $this->actingAs($this->user)->withHeader('User-Agent', $iphone)->withServerVariables(['REMOTE_ADDR' => '112.198.4.20'])
            ->post('/me/devices/register', ['code' => session('code')['code']])->assertSessionHasNoErrors();

        $this->actingAs($this->hr)->get('/admin/devices')->assertOk()
            ->assertSeeInOrder(['Dell Latitude 5440', 'Chrome 141 on Windows', 'IP 203.177.10.5'])
            ->assertSeeInOrder(['Lenovo ThinkPad', 'Safari 18 on iPhone', 'Phone or tablet, not a computer', 'IP 112.198.4.20']);

        // A new code clears the old registration details until it is entered again.
        $dell = IssuedDevice::where('name', 'Dell Latitude 5440')->sole();
        $dell->newActivationCode();
        $this->assertNull($dell->fresh()->activated_ip);
        $this->assertNull($dell->fresh()->browser_label);
    }

    public function test_arrangement_without_device_requirement_allows_any_device(): void
    {
        WfhArrangement::create(['employee_id' => $this->juan->id, 'date_from' => '2026-10-19', 'date_to' => '2026-10-19', 'require_device' => false]);
        $this->actingAs($this->user)->postJson('/me/punch', ['descriptor' => $this->face()])->assertOk();
        $this->assertSame('wfh', $this->juan->punches()->sole()->source);
    }

    public function test_accomplishment_reports_only_on_wfh_days_and_print(): void
    {
        WfhArrangement::create(['employee_id' => $this->juan->id, 'date_from' => '2026-10-12', 'date_to' => '2026-10-30', 'weekdays' => [1]]);
        $this->actingAs($this->user)->get('/me/wfh')->assertOk()->assertSee('Today is a work-from-home day')->assertSee('Mon, Oct 12');
        $this->post('/me/wfh/report', ['work_date' => '2026-10-13', 'accomplishments' => 'x'])->assertSessionHasErrors('work_date'); // Tuesday
        $this->post('/me/wfh/report', ['work_date' => '2026-10-26', 'accomplishments' => 'x'])->assertSessionHasErrors('work_date'); // future
        $this->post('/me/wfh/report', ['work_date' => '2026-10-19', 'accomplishments' => "Drafted 3 exhibitor contracts\nOnline meeting with DTI"])->assertSessionHasNoErrors();
        $this->post('/me/wfh/report', ['work_date' => '2026-10-19', 'accomplishments' => 'Revised: drafted 4 contracts'])->assertSessionHasNoErrors();
        $this->assertSame('Revised: drafted 4 contracts', WfhReport::sole()->accomplishments);

        $this->get('/print/wfh/'.$this->juan->id.'?month=2026-10')->assertOk()
            ->assertSee('WORK-FROM-HOME ACCOMPLISHMENT REPORT')->assertSee('Revised: drafted 4 contracts')->assertSee('Verify this document');
        $this->actingAs($this->hr)->get('/admin/wfh')->assertOk()->assertSee('CRUZ, Juan');
    }

    public function test_ending_an_arrangement_keeps_past_days_and_cancelling_a_future_one(): void
    {
        $past = WfhArrangement::create(['employee_id' => $this->juan->id, 'date_from' => '2026-10-01', 'date_to' => '2026-10-31']);
        $future = WfhArrangement::create(['employee_id' => $this->juan->id, 'date_from' => '2026-11-02', 'date_to' => '2026-11-30']);
        $this->actingAs($this->hr)->post("/admin/wfh/{$past->id}/end");
        $this->assertSame('2026-10-18', $past->fresh()->date_to->toDateString());
        $this->actingAs($this->hr)->post("/admin/wfh/{$future->id}/end");
        $this->assertNotNull($future->fresh()->cancelled_at);
        $this->assertNull(WfhArrangement::forDay($this->juan, now()));
    }

    public function test_csv_import_of_arrangements(): void
    {
        $importer = new WfhImporter;
        [$row, $errors] = $importer->validate(['employee_no' => 'E1', 'date_from' => '11/02/2026', 'date_to' => '2026-11-27', 'days' => 'Mon, Wed', 'issued_computer_only' => 'no']);
        $this->assertSame([], $errors);
        $this->actingAs($this->hr);
        $importer->save($row);
        $a = WfhArrangement::sole();
        $this->assertSame([1, 3], $a->weekdays);
        $this->assertFalse($a->require_device);

        [, $errors] = $importer->validate(['employee_no' => 'E1', 'date_from' => '2026-11-02', 'date_to' => '2026-11-27', 'days' => 'Funday']);
        $this->assertNotEmpty($errors);
    }

    public function test_only_attendance_managers_set_arrangements(): void
    {
        $payroll = User::create(['name' => 'Pay', 'username' => 'pay', 'password' => 'secret123', 'role' => 'payroll']);
        $this->actingAs($payroll)->get('/admin/wfh')->assertOk();
        $this->actingAs($payroll)->post('/admin/wfh', ['employee_ids' => [$this->juan->id], 'date_from' => '2026-10-19', 'date_to' => '2026-10-19'])->assertForbidden();
        $this->actingAs($this->user)->get('/admin/devices')->assertForbidden();
    }

    public function test_sign_in_can_be_limited_to_issued_computers(): void
    {
        $this->actingAs($this->hr)->post('/admin/devices', ['employee_id' => $this->juan->id, 'name' => 'Dell Latitude 5440', 'restrict_login' => 1]);
        $code = session('code')['code'];
        $this->assertTrue($this->juan->fresh()->login_device_only);
        $this->post('/logout');

        // Password is right, but this browser isn't a registered computer: asked for the code, not signed in.
        $this->post('/login', ['username' => 'juan', 'password' => 'secret123'])->assertRedirect('/login/device');
        $this->assertGuest();
        $this->get('/login/device')->assertOk()->assertSee('Use your issued computer');
        $this->post('/login/device', ['code' => 'WRONG-CODE'])->assertSessionHasErrors('code');
        $this->assertGuest();

        // The right code registers this computer and finishes signing in.
        $response = $this->post('/login/device', ['code' => $code])->assertRedirect('/me');
        $this->assertAuthenticatedAs($this->user);
        $cookie = [DeviceCookie::NAME => $response->getCookie(DeviceCookie::NAME)->getValue()];
        $this->withCookies($cookie)->get('/me')->assertOk();

        // Next time, from the same computer: straight in. From another browser: refused.
        $this->post('/logout');
        $this->withCookies($cookie)->post('/login', ['username' => 'juan', 'password' => 'secret123'])->assertRedirect('/me');
        $this->post('/logout');
        $this->flushSession();
        $this->disableCookieEncryption();
        $this->withUnencryptedCookies([])->post('/login', ['username' => 'juan', 'password' => 'secret123'])->assertRedirect('/login/device');
        $this->assertGuest();
    }

    public function test_existing_sessions_without_the_computer_are_signed_out_and_hr_can_lift_the_limit(): void
    {
        $this->actingAs($this->user)->get('/me')->assertOk();
        $this->juan->forceFill(['login_device_only' => true])->save();
        $this->actingAs($this->user->fresh())->get('/me')->assertRedirect('/login');
        $this->assertGuest();

        $this->actingAs($this->hr)->post("/admin/employees/{$this->juan->id}/login-device", ['on' => 0])->assertSessionHas('status');
        $this->assertFalse($this->juan->fresh()->login_device_only);
        $this->post('/logout');
        $this->post('/login', ['username' => 'juan', 'password' => 'secret123'])->assertRedirect('/me');
    }
}
