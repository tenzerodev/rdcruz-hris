<?php

namespace App\Services;

use App\Models\Kiosk;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Production readiness checks, shown on System → Status and by `php artisan hris:check`.
 * Each check is ok, warn (works, but should be fixed) or fail (something is broken).
 */
class SystemHealth
{
    public function __construct(private BackupService $backups) {}

    /** @return list<array{key: string, label: string, status: 'ok'|'warn'|'fail', detail: string, fix: ?string}> */
    public function checks(): array
    {
        $checks = [];
        $add = function (string $key, string $label, string $status, string $detail, ?string $fix = null) use (&$checks) {
            $checks[] = compact('key', 'label', 'status', 'detail', 'fix');
        };

        // Application
        $add('debug', 'Debug mode', config('app.debug') ? 'fail' : 'ok',
            config('app.debug') ? 'On — error pages can show passwords and personal data.' : 'Off.',
            config('app.debug') ? 'Set APP_DEBUG=false in .env.' : null);
        $add('env', 'Environment', app()->environment('production') ? 'ok' : 'warn', 'APP_ENV='.app()->environment().'.',
            app()->environment('production') ? null : 'Set APP_ENV=production on the live server.');

        $url = (string) config('app.url');
        $https = str_starts_with($url, 'https://');
        $add('url', 'Address (APP_URL)', $https ? 'ok' : 'warn', $url ?: 'Not set.',
            $https ? null : 'Use HTTPS. Phone and laptop cameras only work on HTTPS, and QR codes on printouts use this address.');

        // Database
        try {
            $version = DB::selectOne(DB::connection()->getDriverName() === 'sqlite' ? 'select sqlite_version() as v' : 'select version() as v')->v;
            $add('database', 'Database', 'ok', DB::connection()->getDriverName().' '.$version.' · '.DB::connection()->getDatabaseName());
        } catch (Throwable $e) {
            $add('database', 'Database', 'fail', 'Cannot connect: '.$e->getMessage(), 'Check DB_* settings in .env.');
        }
        try {
            $pending = $this->pendingMigrations();
            $add('migrations', 'Database updates', $pending ? 'fail' : 'ok', $pending ? count($pending).' update(s) not applied.' : 'Up to date.',
                $pending ? 'Run: php artisan migrate --force' : null);
        } catch (Throwable) {
            // Reported by the database check.
        }

        // Storage
        $writable = is_writable(storage_path('app/private')) && is_writable(storage_path('logs'));
        $add('storage', 'File storage', $writable ? 'ok' : 'fail', $writable ? 'Writable.' : 'storage/ is not writable by the web server.',
            $writable ? null : 'Give the web server user write access to storage/ and bootstrap/cache/.');
        $linked = file_exists(public_path('storage'));
        $add('storage_link', 'Public files link', $linked ? 'ok' : 'warn', $linked ? 'public/storage is linked.' : 'Logo will not show.',
            $linked ? null : 'Run: php artisan storage:link');
        $free = @disk_free_space(storage_path());
        if ($free !== false) {
            $gb = round($free / 1024 ** 3, 1);
            $add('disk', 'Free disk space', $gb < 2 ? 'warn' : 'ok', "{$gb} GB free.", $gb < 2 ? 'Free up space or move old backups elsewhere.' : null);
        }

        // Email
        $mailer = config('mail.default');
        $mailOk = ! in_array($mailer, ['log', 'array'], true);
        $add('mail', 'Email', $mailOk ? 'ok' : 'warn', $mailOk ? "Sending with {$mailer} (".config("mail.mailers.{$mailer}.host", '—').').' : 'Not set up — notifications go to the log file only.',
            $mailOk ? null : 'Enter your mail server under Email settings below, then send a test.');

        // Background work
        $beat = (int) Cache::get('hris.scheduler_beat', 0);
        $beatOk = $beat && now()->getTimestamp() - $beat < 15 * 60;
        $add('scheduler', 'Scheduled tasks', $beatOk ? 'ok' : 'fail', $beat ? 'Last ran '.now()->setTimestamp($beat)->diffForHumans().'.' : 'Has never run.',
            $beatOk ? null : 'Add the cron entry: * * * * * cd '.base_path().' && php artisan schedule:run (nightly backups, contract reminders and privacy clean-up depend on it).');
        try {
            $failed = DB::table('failed_jobs')->count();
            $add('jobs', 'Failed background jobs', $failed ? 'warn' : 'ok', $failed ? "{$failed} failed." : 'None.', $failed ? 'See storage/logs, then run: php artisan queue:retry all' : null);
        } catch (Throwable) {
            // No queue tables.
        }

        // Backups
        $last = $this->backups->list()->first();
        $fresh = $last && $last['date']->gt(now()->subHours(36));
        $add('backup', 'Backups', $fresh ? 'ok' : ($last ? 'warn' : 'fail'),
            $last ? 'Latest: '.$last['date']->diffForHumans().' ('.number_format($last['size'] / 1024 ** 2, 1).' MB).' : 'No backup yet.',
            $fresh ? null : 'Make a backup now, and copy backups to another machine or drive regularly.');

        // Security
        $staff = User::whereNotIn('role', ['employee'])->whereNull('disabled_at')->get();
        $without = $staff->filter(fn ($u) => ! $u->hasTwoFactor())->count();
        $required = Setting::get('require_2fa_staff') === '1';
        $add('2fa', 'Two-step verification for HR accounts', $without === 0 ? 'ok' : 'warn',
            $without === 0 ? 'All '.$staff->count().' HR account(s) use it.' : "{$without} of {$staff->count()} HR account(s) without it".($required ? ' (they will be asked to set it up).' : '.'),
            $without === 0 || $required ? null : 'Turn on "Require two-step verification for HR accounts" below.');
        $defaultAdmin = User::where('username', 'admin')->where('role', 'admin')->whereNull('password_changed_at')->exists();
        if ($defaultAdmin) {
            $add('admin_password', 'Initial admin password', 'warn', 'The "admin" account still uses the password from installation.', 'Sign in as admin and change it under Sign-in & security.');
        }

        // Kiosks
        $kiosks = Kiosk::where('is_active', true)->get();
        $stale = $kiosks->filter(fn ($k) => ! $k->last_seen_at || $k->last_seen_at->lt(now()->subDay()));
        if ($kiosks->isNotEmpty()) {
            $add('kiosks', 'Kiosks', $stale->isEmpty() ? 'ok' : 'warn',
                $stale->isEmpty() ? $kiosks->count().' kiosk(s) active today.' : 'Not seen in 24 hours: '.$stale->pluck('name')->join(', ').'.',
                $stale->isEmpty() ? null : 'Check that the kiosk device is on and connected.');
        }

        // PHP
        $missing = array_values(array_filter(['pdo', 'mbstring', 'openssl', 'zip', 'fileinfo', 'gd'], fn ($ext) => ! extension_loaded($ext)));
        $add('php', 'PHP', $missing ? 'warn' : 'ok', 'PHP '.PHP_VERSION.($missing ? ' · missing: '.implode(', ', $missing) : '.'),
            $missing ? 'Install the missing PHP extensions.' : null);

        $models = is_file(public_path('vendor/face-api/models/tiny_face_detector_model-weights_manifest.json'));
        $add('face_models', 'Face recognition models', $models ? 'ok' : 'fail', $models ? 'Installed.' : 'Missing from public/vendor/face-api/models.',
            $models ? null : 'Copy the public/vendor folder from the release package.');

        return $checks;
    }

    /** @return array{ok: int, warn: int, fail: int} */
    public function summary(array $checks): array
    {
        $counts = ['ok' => 0, 'warn' => 0, 'fail' => 0];
        foreach ($checks as $c) {
            $counts[$c['status']]++;
        }

        return $counts;
    }

    /** @return list<string> */
    private function pendingMigrations(): array
    {
        $ran = DB::table('migrations')->pluck('migration')->all();
        $files = collect(glob(database_path('migrations/*.php')))->map(fn ($f) => basename($f, '.php'));

        return $files->reject(fn ($m) => in_array($m, $ran, true))->values()->all();
    }
}
