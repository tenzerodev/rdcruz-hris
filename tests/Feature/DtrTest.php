<?php

namespace Tests\Feature;

use App\Http\Middleware\AuthenticateKiosk;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\Kiosk;
use App\Models\Punch;
use App\Models\Setting;
use App\Models\User;
use App\Services\DtrBuilder;
use App\Services\PunchRecorder;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DtrTest extends TestCase
{
    use RefreshDatabase;

    /** A deterministic fake face: a unit-ish vector seeded per person. */
    private function face(int $seed, float $noise = 0.0): array
    {
        mt_srand($seed);
        $v = [];
        for ($i = 0; $i < 128; $i++) {
            $v[] = (mt_rand(-1000, 1000) / 1000) * 0.15;
        }
        if ($noise) {
            mt_srand($seed * 7919 + 1);
            $v = array_map(fn ($x) => $x + (mt_rand(-1000, 1000) / 1000) * $noise, $v);
        }

        return $v;
    }

    private function employee(int $seed, array $attrs = []): Employee
    {
        $e = Employee::create(['employee_no' => 'E'.$seed, 'last_name' => 'Cruz'.$seed, 'first_name' => 'Juan', 'consent_at' => now(), ...$attrs]);
        $e->faceSamples()->create(['descriptor' => $this->face($seed)]);

        return $e;
    }

    private function kioskCookie(): array
    {
        $kiosk = Kiosk::create(['name' => 'Lobby']);

        return [AuthenticateKiosk::COOKIE => $kiosk->id.'|'.$kiosk->issueToken()];
    }

    public function test_kiosk_recognizes_and_records_punches_in_sequence(): void
    {
        $juan = $this->employee(1);
        $this->employee(2);
        $cookie = $this->kioskCookie();

        Carbon::setTestNow('2026-10-19 07:55:00');
        $this->withCredentials()->withCookies($cookie)->postJson('/kiosk/punch', ['descriptor' => $this->face(1, 0.01), 'liveness' => true])
            ->assertOk()->assertJson(['ok' => true, 'punch' => ['type' => 'am_in']]);

        // Duplicate within the window is rejected.
        Carbon::setTestNow('2026-10-19 07:56:00');
        $this->withCredentials()->withCookies($cookie)->postJson('/kiosk/punch', ['descriptor' => $this->face(1)])->assertStatus(409);

        Carbon::setTestNow('2026-10-19 17:05:00');
        $this->withCredentials()->withCookies($cookie)->postJson('/kiosk/punch', ['descriptor' => $this->face(1)])
            ->assertOk()->assertJson(['punch' => ['type' => 'pm_out']]);

        $this->assertSame(['am_in', 'pm_out'], $juan->punches()->orderBy('punched_at')->pluck('type')->all());

        // A stranger is not recognized.
        $this->withCredentials()->withCookies($cookie)->postJson('/kiosk/punch', ['descriptor' => $this->face(99)])->assertNotFound();
    }

    public function test_kiosk_requires_activated_device(): void
    {
        $this->employee(1);
        $this->postJson('/kiosk/punch', ['descriptor' => $this->face(1)])->assertForbidden();
        $this->withCookies([AuthenticateKiosk::COOKIE => '1|wrong'])->get('/kiosk')->assertForbidden();
    }

    public function test_personal_device_verifies_own_face_and_geofence(): void
    {
        $user = User::create(['name' => 'Juan', 'username' => 'juan', 'password' => 'secret123', 'role' => 'employee']);
        $juan = $this->employee(1);
        $juan->update(['user_id' => $user->id]);
        $this->employee(2);

        Carbon::setTestNow('2026-10-19 08:10:00');
        // Someone else's face on Juan's phone is rejected.
        $this->actingAs($user)->postJson('/me/punch', ['descriptor' => $this->face(2)])->assertForbidden();

        Setting::put(['geofence_enabled' => '1', 'geofence_lat' => '14.5547', 'geofence_lng' => '121.0244', 'geofence_radius' => '150']);
        $this->actingAs($user)->postJson('/me/punch', ['descriptor' => $this->face(1), 'latitude' => 14.60, 'longitude' => 121.0])
            ->assertForbidden()->assertJsonPath('ok', false);
        $this->actingAs($user)->postJson('/me/punch', ['descriptor' => $this->face(1), 'latitude' => 14.5548, 'longitude' => 121.0245, 'accuracy' => 10])
            ->assertOk()->assertJsonPath('punch.type', 'am_in');

        $this->assertSame('personal', $juan->punches()->sole()->source);
    }

    public function test_next_type_handles_lunch_break(): void
    {
        $e = $this->employee(1);
        $r = app(PunchRecorder::class);
        $this->assertSame('am_in', $r->nextType($e, Carbon::parse('2026-10-19 07:50')));
        $this->assertSame('pm_in', $r->nextType($e, Carbon::parse('2026-10-19 12:50')));

        Punch::create(['employee_id' => $e->id, 'work_date' => '2026-10-19', 'type' => 'am_in', 'punched_at' => '2026-10-19 07:50', 'source' => 'kiosk']);
        $this->assertSame('am_out', $r->nextType($e, Carbon::parse('2026-10-19 12:01')));
        $this->assertSame('pm_out', $r->nextType($e, Carbon::parse('2026-10-19 17:00')));
    }

    public function test_once_a_day_mode_records_one_time_in_and_one_time_out(): void
    {
        $e = $this->employee(1, ['punch_mode' => 'two']);
        $r = app(PunchRecorder::class);

        $in = $r->record($e, ['source' => 'kiosk'], Carbon::parse('2026-10-19 07:50'));
        $this->assertSame('am_in', $in->type);

        // Noon is not a lunch punch: it is the time out, and the day is then complete.
        $this->assertSame('am_out', $r->nextType($e, Carbon::parse('2026-10-19 12:30')));
        $out = $r->record($e, ['source' => 'kiosk'], Carbon::parse('2026-10-19 17:05'));
        $this->assertSame('pm_out', $out->type);
        $this->assertSame('Time Out', $e->punchLabel($out->type));
        $this->assertNull($r->nextType($e, Carbon::parse('2026-10-19 17:30')));

        // A second time in is refused even when picked by hand.
        $f = $this->employee(2, ['punch_mode' => 'two']);
        $r->record($f, ['source' => 'kiosk'], Carbon::parse('2026-10-19 08:00'));
        $this->expectExceptionMessage('Time In already recorded today.');
        $r->record($f, ['source' => 'kiosk', 'type' => 'pm_in'], Carbon::parse('2026-10-19 13:00'));
    }

    public function test_punch_mode_follows_agency_default_unless_set_on_employee(): void
    {
        $default = $this->employee(1);
        $four = $this->employee(2, ['punch_mode' => 'four']);
        Setting::put(['punch_mode' => 'two']);

        $this->assertTrue($default->punchesOncePerDay());
        $this->assertFalse($four->punchesOncePerDay());
    }

    public function test_undertime_rules(): void
    {
        $b = app(DtrBuilder::class);
        $h = ['am_in' => '08:00', 'am_out' => '12:00', 'pm_in' => '13:00', 'pm_out' => '17:00'];
        $times = fn (?string $a, ?string $b2, ?string $c, ?string $d) => [
            'am_in' => $a ? Carbon::parse("2026-10-19 $a") : null, 'am_out' => $b2 ? Carbon::parse("2026-10-19 $b2") : null,
            'pm_in' => $c ? Carbon::parse("2026-10-19 $c") : null, 'pm_out' => $d ? Carbon::parse("2026-10-19 $d") : null,
        ];

        $this->assertSame(0, $b->undertime($times('07:55', '12:00', '12:58', '17:01'), $h));
        $this->assertSame(15 + 10 + 5 + 30, $b->undertime($times('08:15', '11:50', '13:05', '16:30'), $h));
        $this->assertSame(0, $b->undertime($times('08:05', null, null, '17:00'), $h, grace: 5)); // 2-punch day within grace
        $this->assertSame(240, $b->undertime($times('08:00', '12:00', null, null), $h)); // PM half-day absence
        $this->assertSame(0, $b->undertime($times('08:00', '12:00', null, null), $h, halfDayAsUndertime: false));
    }

    private function punchDay(Employee $e, string $date, array $times): void
    {
        foreach ($times as $type => $time) {
            Punch::create(['employee_id' => $e->id, 'work_date' => $date, 'type' => $type, 'punched_at' => "$date $time", 'source' => 'kiosk']);
        }
    }

    public function test_overtime_rules(): void
    {
        $b = app(DtrBuilder::class);
        $h = ['am_in' => '08:00', 'am_out' => '12:00', 'pm_in' => '13:00', 'pm_out' => '17:00'];
        $rules = ['min' => 30, 'rounding' => 1, 'early' => false];
        $t = fn (?string $a, ?string $b2, ?string $c, ?string $d) => [
            'am_in' => $a ? Carbon::parse("2026-10-19 $a") : null, 'am_out' => $b2 ? Carbon::parse("2026-10-19 $b2") : null,
            'pm_in' => $c ? Carbon::parse("2026-10-19 $c") : null, 'pm_out' => $d ? Carbon::parse("2026-10-19 $d") : null,
        ];

        $this->assertSame(95, $b->overtime($t('07:30', '12:00', '13:00', '18:35'), $h, true, $rules)); // after hours only
        $this->assertSame(0, $b->overtime($t('08:00', null, null, '17:20'), $h, true, $rules));          // under the minimum
        $this->assertSame(125, $b->overtime($t('07:30', null, null, '18:35'), $h, true, [...$rules, 'early' => true]));
        $this->assertSame(90, $b->overtime($t('07:30', null, null, '18:35'), $h, true, [...$rules, 'rounding' => 30]));
        $this->assertSame(420, $b->overtime($t('09:00', null, null, '17:00'), $h, false, $rules));        // rest day, less lunch
        $this->assertSame(300, $b->overtime($t('08:00', '11:00', '13:00', '15:00'), $h, false, $rules)); // rest day, 4 punches
    }

    public function test_form_48_prints_with_weekends_holidays_and_totals(): void
    {
        $admin = User::create(['name' => 'Admin', 'username' => 'admin', 'password' => 'secret123', 'role' => 'admin']);
        $e = $this->employee(1, ['middle_name' => 'Santos', 'supervisor_name' => 'Maria Reyes']);
        Holiday::create(['date' => '2026-11-30', 'name' => 'Bonifacio Day']);
        Punch::create(['employee_id' => $e->id, 'work_date' => '2026-11-02', 'type' => 'am_in', 'punched_at' => '2026-11-02 08:20', 'source' => 'kiosk']);
        Punch::create(['employee_id' => $e->id, 'work_date' => '2026-11-02', 'type' => 'pm_out', 'punched_at' => '2026-11-02 17:00', 'source' => 'kiosk']);

        $r = app(DtrBuilder::class)->build($e, 2026, 11);
        $this->assertCount(31, $r['rows']);
        $this->assertSame(20, $r['total_minutes']);
        $this->assertSame('SUNDAY', $r['rows'][0]['note']);
        $this->assertSame('Bonifacio Day', $r['rows'][29]['note']);

        $this->actingAs($admin)->get('/admin/dtr/print?month=2026-11')->assertOk()
            ->assertSee('Civil Service Form No. 48')->assertSee('DAILY TIME RECORD')->assertSee('NOVEMBER 2026')
            ->assertSee('Juan S. Cruz1')->assertSee('Maria Reyes')->assertSee('8:20')->assertSee('Bonifacio Day');
    }

    public function test_enrollment_requires_consent_and_blocks_duplicate_faces(): void
    {
        $admin = User::create(['name' => 'Admin', 'username' => 'admin', 'password' => 'secret123', 'role' => 'admin']);
        $this->employee(1);
        $new = Employee::create(['employee_no' => 'E5', 'last_name' => 'Santos', 'first_name' => 'Ana']);
        $samples = fn (int $seed) => collect(range(1, 3))->map(fn ($i) => ['descriptor' => $this->face($seed, 0.005 * $i)])->all();

        $this->actingAs($admin)->postJson("/admin/employees/{$new->id}/samples", ['samples' => $samples(5)])->assertUnprocessable();
        $this->actingAs($admin)->postJson("/admin/employees/{$new->id}/samples", ['samples' => $samples(1), 'consent' => true])
            ->assertUnprocessable()->assertJsonPath('ok', false);
        $this->actingAs($admin)->postJson("/admin/employees/{$new->id}/samples", ['samples' => $samples(5), 'consent' => true])
            ->assertOk()->assertJsonPath('total', 3);
        $this->assertNotNull($new->fresh()->consent_at);
    }

    public function test_pages_render_and_roles_are_enforced(): void
    {
        $admin = User::create(['name' => 'Admin', 'username' => 'admin', 'password' => 'secret123', 'role' => 'admin']);
        $user = User::create(['name' => 'Juan', 'username' => 'juan', 'password' => 'secret123', 'role' => 'employee']);
        $this->employee(1)->update(['user_id' => $user->id]);

        foreach (['/admin', '/admin/employees', '/admin/employees/create', '/admin/employees/1/edit', '/admin/employees/1/enroll', '/admin/punches', '/admin/punches/create', '/admin/dtr', '/admin/kiosks', '/admin/holidays', '/admin/settings', '/admin/audit'] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
        foreach (['/me', '/me/punch', '/me/dtr?month=2026-10', '/me/password'] as $url) {
            $this->actingAs($user)->get($url)->assertOk();
        }
        $this->actingAs($user)->get('/admin')->assertForbidden();
        $this->post('/logout');
        $this->post('/login', ['username' => 'juan', 'password' => 'secret123'])->assertRedirect('/me');
    }
}
