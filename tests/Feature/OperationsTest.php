<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\FaceSample;
use App\Models\Holiday;
use App\Models\Punch;
use App\Models\Setting;
use App\Models\User;
use App\Services\BackupService;
use App\Services\DtrBuilder;
use App\Services\HabitualReport;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OperationsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-26 09:00:00');
        Storage::fake('local');
        Storage::fake('public');
        $this->admin = User::create(['name' => 'Admin', 'username' => 'admin', 'password' => 'secret123', 'role' => 'admin']);
    }

    private function employee(array $attrs = []): Employee
    {
        static $n = 0;
        $n++;

        return Employee::create(['employee_no' => "E$n", 'last_name' => "Cruz$n", 'first_name' => 'Juan', 'schedule_type' => 'flexi', 'office' => 'FAD', ...$attrs]);
    }

    private function day(Employee $e, string $date, string $in, string $out = '18:00'): void
    {
        Punch::create(['employee_id' => $e->id, 'work_date' => $date, 'type' => 'am_in', 'punched_at' => "$date $in", 'source' => 'kiosk']);
        Punch::create(['employee_id' => $e->id, 'work_date' => $date, 'type' => 'pm_out', 'punched_at' => "$date $out", 'source' => 'kiosk']);
    }

    public function test_backup_and_restore_round_trip(): void
    {
        $e = $this->employee(['tin' => '111-222-333']);
        Holiday::create(['date' => '2026-12-24', 'name' => 'Christmas Eve']);
        Storage::disk('local')->put('201/1/appointment.pdf', 'PDF');
        $backups = app(BackupService::class);

        $name = $backups->create('test');
        $manifest = $backups->inspect($backups->path($name));
        $this->assertSame(1, $manifest['tables']['employees']);
        $this->assertSame(1, $manifest['files']);

        // Things change after the backup...
        $e->update(['last_name' => 'Changed']);
        Holiday::query()->delete();
        $this->employee();
        Storage::disk('local')->delete('201/1/appointment.pdf');

        // ...and a restore brings back the backed-up state, keeping a safety copy.
        $safety = $backups->restore($backups->path($name));
        $this->assertSame(['Cruz'.substr($e->employee_no, 1)], Employee::pluck('last_name')->all());
        $this->assertSame('111-222-333', Employee::first()->tin);
        $this->assertDatabaseHas('holidays', ['name' => 'Christmas Eve']);
        $this->assertSame('PDF', Storage::disk('local')->get('201/1/appointment.pdf'));
        $this->assertTrue($backups->list()->contains('name', $safety));
    }

    public function test_backup_pages_and_restore_needs_confirmation(): void
    {
        $this->actingAs($this->admin)->post('/admin/backups')->assertSessionHas('status');
        $name = app(BackupService::class)->list()->first()['name'];
        $this->get('/admin/backups')->assertOk()->assertSee($name);
        $this->get("/admin/backups/{$name}")->assertOk()->assertHeader('content-type', 'application/zip');
        $this->get('/admin/backups/..%2F.env')->assertNotFound();

        $this->post('/admin/backups/restore', ['name' => $name, 'confirm' => 'restore', 'password' => 'secret123'])->assertSessionHasErrors('confirm');
        $this->post('/admin/backups/restore', ['name' => $name, 'confirm' => 'RESTORE', 'password' => 'wrong'])->assertSessionHasErrors('password');
        $this->post('/admin/backups/restore', ['name' => $name, 'confirm' => 'RESTORE', 'password' => 'secret123'])->assertRedirect('/login');
        $this->assertGuest();
        $this->assertDatabaseHas('users', ['username' => 'admin']);
    }

    public function test_system_status_page_settings_and_check_command(): void
    {
        $this->actingAs($this->admin)->get('/admin/system')->assertOk()->assertSee('Health checks')->assertSee('Scheduled tasks');
        $this->put('/admin/system/mail', ['mail_host' => 'smtp.agency.gov.ph', 'mail_port' => 587, 'mail_encryption' => 'tls',
            'mail_username' => 'hris', 'mail_password' => 'mail-secret', 'mail_from_address' => 'hris@agency.gov.ph'])->assertSessionHasNoErrors();
        $this->assertNotSame('mail-secret', Setting::get('mail_password'));
        $this->put('/admin/system/privacy', ['dpo_name' => 'Atty. Dela Cruz', 'dpo_email' => 'dpo@agency.gov.ph', 'face_purge_days' => 30])->assertSessionHasNoErrors();
        $this->get('/privacy')->assertOk()->assertSee('Atty. Dela Cruz')->assertSee('dpo@agency.gov.ph');
        $this->put('/admin/system/security', ['session_idle_minutes' => 15, 'lockout_attempts' => 5, 'lockout_minutes' => 10, 'password_min_length' => 12, 'require_2fa_staff' => 1])
            ->assertSessionHasNoErrors();
        $this->assertSame('1', Setting::get('require_2fa_staff'));

        $this->artisan('hris:check')->expectsOutputToContain('Backups')->assertExitCode(1); // no backup and no scheduler yet
    }

    public function test_tardiness_counts_and_habitual_rules(): void
    {
        $e = $this->employee();
        $this->day($e, '2026-10-05', '09:30');  // flexi: in by 10:00 → not tardy
        $this->day($e, '2026-10-06', '10:05');  // tardy
        $fixed = $this->employee(['schedule_type' => 'fixed']);
        $this->day($fixed, '2026-10-05', '08:01', '17:00'); // tardy against 8:00
        $this->day($fixed, '2026-10-06', '08:00', '17:00');

        $dtr = app(DtrBuilder::class);
        $this->assertSame(1, $dtr->build($e, 2026, 10)['tardy_days']);
        $this->assertSame(1, $dtr->build($fixed, 2026, 10)['tardy_days']);

        // No absences before attendance tracking began (first punch: Oct 5) unless HR sets an earlier start.
        $this->assertSame(0, $dtr->build($e, 2026, 9)['days_absent']);
        $this->assertSame(14, $dtr->build($e, 2026, 10)['days_absent']); // Oct 7–26 working days without punches
        Setting::put(['attendance_start_date' => '2026-10-01']);
        $this->assertSame(16, $dtr->build($e, 2026, 10)['days_absent']); // plus Oct 1–2

        $report = app(HabitualReport::class);
        $this->assertStringContainsString('2nd semester', $report->flag([8 => true, 11 => true], 2));
        $this->assertStringContainsString('consecutive', $report->flag([6 => true, 7 => true], 2)); // straddles semesters
        $this->assertNull($report->flag([3 => true], 2));
        $this->assertNull($report->flag([1 => true, 2 => true, 9 => true], 3));
        $this->assertStringContainsString('1st semester', $report->flag([1 => true, 3 => true, 5 => true], 3));
    }

    public function test_habitual_report_flags_tardy_employees(): void
    {
        $e = $this->employee(['last_name' => 'Latecomer']);
        foreach (['2026-09', '2026-10'] as $month) {
            $d = Carbon::parse("$month-01");
            $count = 0;
            while ($count < 10) {
                if ($d->isWeekday()) {
                    $this->day($e, $d->toDateString(), '10:30');
                    $count++;
                }
                $d->addDay();
            }
        }
        $this->actingAs($this->admin)->get('/admin/reports/habitual?year=2026')->assertOk()
            ->assertSee('LATECOMER')->assertSee('Habitually tardy')->assertSee('Sep, Oct');
        $csv = $this->get('/admin/reports/habitual?year=2026&format=csv')->assertOk()->streamedContent();
        $this->assertStringContainsString('LATECOMER', $csv);
    }

    public function test_employee_can_download_their_data_and_withdraw_face_consent(): void
    {
        $user = User::create(['name' => 'Juan', 'username' => 'juan', 'password' => 'secret123', 'role' => 'employee']);
        $e = $this->employee(['user_id' => $user->id, 'tin' => '999-888', 'consent_at' => now()]);
        FaceSample::create(['employee_id' => $e->id, 'descriptor' => array_fill(0, 128, 0.1)]);
        Storage::disk('local')->put('snapshots/x.jpg', 'jpg');
        Punch::create(['employee_id' => $e->id, 'work_date' => '2026-10-23', 'type' => 'am_in', 'punched_at' => '2026-10-23 08:00', 'source' => 'kiosk', 'snapshot_path' => 'snapshots/x.jpg']);

        $this->actingAs($user)->get('/me/privacy')->assertOk()->assertSee('1 face template');
        $json = json_decode($this->get('/me/privacy/export')->assertOk()->streamedContent(), true);
        $this->assertSame('999-888', $json['profile']['tin']);
        $this->assertCount(1, $json['time_records']);

        $this->post('/me/privacy/withdraw', ['password' => 'secret123'])->assertSessionHas('status');
        $this->assertSame(0, $e->faceSamples()->count());
        $this->assertNull($e->fresh()->consent_at);
        Storage::disk('local')->assertMissing('snapshots/x.jpg');
        $this->assertDatabaseHas('audit_logs', ['action' => 'privacy.face_consent_withdrawn']);
    }

    public function test_face_data_of_separated_employees_is_purged_after_retention(): void
    {
        $left = $this->employee(['is_active' => false, 'date_separated' => '2026-09-01', 'consent_at' => now()]);
        $recent = $this->employee(['is_active' => false, 'date_separated' => '2026-10-20', 'consent_at' => now()]);
        foreach ([$left, $recent] as $e) {
            FaceSample::create(['employee_id' => $e->id, 'descriptor' => array_fill(0, 128, 0.1)]);
        }

        $this->artisan('hris:purge-separated')->assertSuccessful();
        $this->assertSame(0, $left->faceSamples()->count());
        $this->assertNotNull($left->fresh()->face_purged_at);
        $this->assertSame(1, $recent->faceSamples()->count());
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'privacy.face_data_purged')->count());
    }
}
