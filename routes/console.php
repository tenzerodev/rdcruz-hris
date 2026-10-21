<?php

use App\Http\Controllers\Admin\RecruitmentController;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\Punch;
use App\Models\Setting;
use App\Models\Vacancy;
use App\Services\BackupService;
use App\Services\LeaveService;
use App\Services\Reminders;
use App\Services\SystemHealth;
use App\Support\Agencies;
use App\Support\Notify;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

// In multi-agency mode every scheduled task runs once per agency.
$each = fn (string $command): string => Agencies::enabled() ? 'hris:agency:run '.escapeshellarg($command) : $command;

Artisan::command('dtr:prune-snapshots {--days=}', function () {
    $days = (int) ($this->option('days') ?: Setting::get('snapshot_retention_days') ?: config('dtr.snapshot_retention_days'));
    $cutoff = now()->subDays($days)->toDateString();
    $count = 0;

    Punch::whereNotNull('snapshot_path')->where('work_date', '<', $cutoff)
        ->chunkById(500, function ($punches) use (&$count) {
            foreach ($punches as $p) {
                Storage::disk('local')->delete($p->snapshot_path);
                $p->forceFill(['snapshot_path' => null])->saveQuietly();
                $count++;
            }
        });

    $this->info("Deleted {$count} snapshots older than {$days} days.");
})->purpose('Delete punch snapshots past the retention period (Data Privacy Act: keep only as long as needed)');

Schedule::command($each('dtr:prune-snapshots'))->dailyAt('02:00');

Artisan::command('hris:post-month {month : YYYY-MM}', function (LeaveService $leaves) {
    [$y, $m] = array_map('intval', explode('-', $this->argument('month')));
    $s = $leaves->postMonth($y, $m);
    $this->info("Posted {$this->argument('month')}: {$s['accrued']} accruals, {$s['undertime']} undertime charges, {$s['coc']} COC credits.");
})->purpose('Post leave credits, undertime and COC for a month to the leave cards');

Artisan::command('hris:post-year {year}', function (LeaveService $leaves) {
    $s = $leaves->postYearEnd((int) $this->argument('year'));
    $this->info("Year-end posted: {$s['forced']} forced-leave deductions, SPL granted to {$s['spl']}.");
})->purpose('Year-end: forced leave deduction, SPL lapse and next year SPL grant');

Artisan::command('hris:contract-digest', function () {
    $soon = Employee::where('is_active', true)->whereNotNull('contract_end')
        ->whereDate('contract_end', '<=', today()->addDays(config('hris.contract_warning_days')))->orderBy('contract_end')->get();
    if ($soon->isEmpty()) {
        return $this->info('No contracts ending soon.');
    }
    Notify::hr('Contracts ending in the next '.config('hris.contract_warning_days').' days ('.$soon->count().')',
        $soon->map(fn ($e) => "{$e->full_name} — {$e->type_label}".($e->agency_name ? " ({$e->agency_name})" : '').', ends '.$e->contract_end->format('M j, Y'))->all(),
        route('admin.reports.workforce'));
    $this->info("Sent digest for {$soon->count()} contract(s).");
})->purpose('Email HR the contracts (COS, JO, service providers) ending soon');

Schedule::command($each('hris:contract-digest'))->weeklyOn(1, '07:00');

Artisan::command('hris:reminders', function (Reminders $reminders) {
    $sent = $reminders->send();
    $this->info('Reminders sent: '.collect($sent)->map(fn ($n, $k) => "$k $n")->join(', ').'.');
})->purpose('Remind supervisors of waiting approvals, HR of step increments, and employees of SALN and IPCR deadlines');

Schedule::command($each('hris:reminders'))->dailyAt('07:30');

Artisan::command('hris:backup {--scheduled : Only run if nightly backups are on} {--snapshots : Include punch photos}', function (BackupService $backups) {
    if ($this->option('scheduled') && Setting::get('backup_enabled') !== '1') {
        return $this->info('Nightly backups are off (System → Backups).');
    }
    $name = $backups->create($this->option('scheduled') ? 'nightly' : 'manual', $this->option('snapshots') ?: null);
    $pruned = $backups->prune((int) Setting::get('backup_keep', '14'));
    AuditLog::create(['action' => 'backup.created', 'details' => ['file' => $name, 'by' => 'command']]);
    $this->info("Backup saved: storage/app/private/backups/{$name}".($pruned ? " ({$pruned} old backup(s) removed)" : ''));
})->purpose('Back up all HRIS data and uploaded files to one .zip');

Schedule::command($each('hris:backup --scheduled'))->dailyAt('01:00');

Artisan::command('hris:restore {file : Path to a backup .zip} {--force : Do not ask for confirmation}', function (BackupService $backups) {
    $path = $this->argument('file');
    if (! is_file($path)) {
        $path = storage_path('app/private/backups/'.$path);
    }
    if (! is_file($path)) {
        return $this->error('Backup file not found.');
    }
    $manifest = $backups->inspect($path);
    $this->warn('This replaces ALL current data with the backup made '.$manifest['created_at'].' ('.($manifest['agency'] ?? '').').');
    if (! $this->option('force') && ! $this->confirm('Continue?')) {
        return $this->info('Cancelled.');
    }
    $safety = $backups->restore($path);
    AuditLog::create(['action' => 'backup.restored', 'details' => ['file' => basename($path), 'safety_backup' => $safety, 'by' => 'command']]);
    $this->info("Restored. Safety backup of the previous data: {$safety}");
})->purpose('Restore a backup made by hris:backup (replaces all data)');

Artisan::command('hris:check', function (SystemHealth $health) {
    $checks = $health->checks();
    $this->table(['', 'Check', 'Result', 'What to do'], array_map(fn ($c) => [
        ['ok' => '✓', 'warn' => '!', 'fail' => '✗'][$c['status']], $c['label'], $c['detail'], $c['fix'] ?? '',
    ], $checks));
    $s = $health->summary($checks);
    $this->line("{$s['ok']} OK · {$s['warn']} to improve · {$s['fail']} to fix");

    return $s['fail'] > 0 ? 1 : 0;
})->purpose('Check that this installation is ready for production');

Artisan::command('hris:purge-separated', function () {
    $days = (int) Setting::get('face_purge_days', '30');
    $count = 0;
    Employee::where('is_active', false)->whereNull('face_purged_at')
        ->where(fn ($q) => $q->whereNull('date_separated')->orWhereDate('date_separated', '<=', today()->subDays($days)))
        ->each(function (Employee $e) use (&$count, $days) {
            if (! $e->date_separated && $e->updated_at->gt(now()->subDays($days))) {
                return; // No separation date: wait the same number of days after the record was deactivated.
            }
            $templates = $e->faceSamples()->delete();
            $e->punches()->whereNotNull('snapshot_path')->each(function ($p) {
                Storage::disk('local')->delete($p->snapshot_path);
                $p->forceFill(['snapshot_path' => null])->saveQuietly();
            });
            $e->forceFill(['face_purged_at' => now(), 'consent_at' => null])->saveQuietly();
            AuditLog::create(['action' => 'privacy.face_data_purged', 'subject_type' => 'Employee', 'subject_id' => $e->id, 'details' => ['templates' => $templates, 'by' => 'schedule']]);
            $count++;
        });
    $this->info("Deleted face data of {$count} separated employee(s).");

    // Applicants not hired: delete their files a while after hiring is finished.
    $months = (int) config('hris.recruitment.retention_months', 12);
    $applicants = 0;
    Vacancy::whereIn('status', ['filled', 'cancelled'])->where('updated_at', '<', now()->subMonths($months))->each(function (Vacancy $v) use (&$applicants) {
        $applicants += RecruitmentController::purgeApplicantFiles($v);
    });
    if ($applicants) {
        AuditLog::create(['action' => 'recruitment.files_purged', 'details' => ['applicants' => $applicants, 'by' => 'schedule']]);
    }
    $this->info("Deleted files of {$applicants} applicant(s) not hired.");
})->purpose('Delete face data of employees who left and files of applicants not hired, after the retention period (Data Privacy Act)');

Schedule::command($each('hris:purge-separated'))->dailyAt('02:30');

// Lets System status confirm the cron entry is working (for every agency in multi-agency mode).
Schedule::call(function () {
    $beat = fn () => Cache::forever('hris.scheduler_beat', now()->getTimestamp());
    Agencies::enabled() ? Agencies::each(fn () => $beat()) : $beat();
})->everyFiveMinutes()->name('hris-heartbeat');

// ---- Several agencies on one server (HRIS_MULTI_AGENCY=true) ----------------------------------

Artisan::command('hris:agency:add {id : Short name, e.g. citem} {--host=* : Web address(es), e.g. hris.citem.gov.ph} {--name= : Agency name}
    {--database= : Database name (default hris_<id>)} {--existing : Register this installation\'s current database and files as this agency}', function () {
    $id = strtolower($this->argument('id'));
    if (! preg_match('/^[a-z0-9_-]{2,40}$/', $id)) {
        return $this->error('Use 2–40 lowercase letters, numbers, - or _.');
    }
    if (Agencies::find($id)) {
        return $this->error("Agency “{$id}” already exists.");
    }
    $hosts = array_values(array_filter(array_map('strtolower', $this->option('host'))));
    if (! $hosts) {
        return $this->error('Give at least one --host.');
    }
    foreach ($hosts as $host) {
        if ($other = Agencies::forHost($host)) {
            return $this->error("{$host} is already used by {$other['id']}.");
        }
    }
    $connection = config('database.default');
    $database = $this->option('existing') ? config("database.connections.$connection.database") : ($this->option('database') ?: 'hris_'.$id);
    if (! $this->option('existing')) {
        if (DB::connection()->getDriverName() === 'sqlite') {
            touch($database);
        } else {
            if (! preg_match('/^[A-Za-z0-9_]+$/', $database)) {
                return $this->error('Database names can only have letters, numbers and _.');
            }
            DB::statement("CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        }
    }
    $agency = ['id' => $id, 'name' => $this->option('name') ?: strtoupper($id), 'hosts' => $hosts, 'database' => $database, 'own_storage' => ! $this->option('existing')];
    Agencies::save(Agencies::all() + [$id => $agency]);

    Agencies::activate($agency);
    $this->call('migrate', ['--force' => true]);
    if ($this->option('name') && ! $this->option('existing')) {
        Setting::put(['agency_name' => $this->option('name')]);
    }
    $this->info("Added {$agency['name']}. Open https://{$hosts[0]} to create its administrator account.");
})->purpose('Add an agency (its own database, files and logins) to a multi-agency server');

Artisan::command('hris:agency:list', function () {
    $this->table(['ID', 'Name', 'Web address', 'Database', 'Files'], array_map(fn ($a) => [
        $a['id'], $a['name'], implode(', ', $a['hosts']), $a['database'], $a['own_storage'] ? 'storage/app/agencies/'.$a['id'] : 'storage/app (shared)',
    ], Agencies::all()));
    $this->line(Agencies::enabled() ? 'Multi-agency mode is ON.' : 'Multi-agency mode is OFF — set HRIS_MULTI_AGENCY=true in .env to use these.');
})->purpose('List the agencies on this server');

Artisan::command('hris:agency:run {cmd : Command to run, in quotes} {--agency= : Only this agency}', function () {
    $agencies = $this->option('agency') ? array_filter([Agencies::find($this->option('agency'))]) : Agencies::all();
    if (! $agencies) {
        return $this->error('No agencies found.');
    }
    $failed = 0;
    foreach ($agencies as $agency) {
        Agencies::activate($agency);
        $this->line("<info>[{$agency['id']}]</info> {$this->argument('cmd')}");
        $failed += Artisan::call($this->argument('cmd'), [], $this->output) ? 1 : 0;
    }

    return $failed ? 1 : 0;
})->purpose('Run a command for every agency (e.g. "migrate --force" after an update)');
