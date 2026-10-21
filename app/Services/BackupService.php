<?php

namespace App\Services;

use App\Models\Setting;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * Full backups as one .zip: every table as JSON lines, plus uploaded files (logo, 201 documents and,
 * optionally, punch photos). Works the same on MySQL/MariaDB and SQLite, without mysqldump.
 *
 * Encrypted columns (government IDs, 2FA secrets, mail password) stay encrypted inside the backup,
 * so a backup can only be restored on a server with the same APP_KEY.
 */
class BackupService
{
    public const DIR = 'backups';

    /** Tables that hold temporary data and are never backed up or restored. */
    private const SKIP = ['cache', 'cache_locks', 'sessions', 'jobs', 'job_batches', 'failed_jobs', 'migrations', 'password_reset_tokens'];

    /** Folders on the private disk that are not part of a backup. */
    private const SKIP_DIRS = [self::DIR, 'imports'];

    /** @return list<string> */
    public function tables(): array
    {
        $driver = DB::connection()->getDriverName();
        $schema = $driver === 'sqlite' ? 'main' : DB::connection()->getDatabaseName();

        return array_values(array_diff(Schema::getTableListing($schema, false), self::SKIP));
    }

    /** Makes a backup and returns its file name. */
    public function create(string $label = 'manual', ?bool $snapshots = null): string
    {
        $snapshots ??= Setting::get('backup_include_snapshots') === '1';
        $disk = Storage::disk('local');
        $disk->makeDirectory(self::DIR);
        $name = 'hris-backup-'.now()->format('Ymd-His').'-'.Str::slug($label).'.zip';
        $path = $disk->path(self::DIR.'/'.$name);
        $work = $disk->path(self::DIR.'/tmp-'.Str::random(8));
        File::ensureDirectoryExists($work);

        try {
            $zip = new ZipArchive;
            if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Could not create the backup file. Check that storage/app/private is writable.');
            }

            $counts = [];
            foreach ($this->tables() as $table) {
                $file = "{$work}/{$table}.jsonl";
                $handle = fopen($file, 'w');
                $counts[$table] = 0;
                foreach (DB::table($table)->cursor() as $row) {
                    fwrite($handle, json_encode($row, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)."\n");
                    $counts[$table]++;
                }
                fclose($handle);
                $zip->addFile($file, "database/{$table}.jsonl");
            }

            $files = 0;
            foreach (['public' => [], 'local' => self::SKIP_DIRS] as $diskName => $skip) {
                $source = Storage::disk($diskName);
                foreach ($source->allFiles() as $relative) {
                    $top = Str::before($relative, '/');
                    if (in_array($top, $skip, true) || str_starts_with(basename($relative), '.')
                        || (! $snapshots && $top === 'snapshots')) {
                        continue;
                    }
                    $zip->addFile($source->path($relative), "files/{$diskName}/{$relative}");
                    $files++;
                }
            }

            $zip->addFromString('manifest.json', json_encode([
                'app' => 'HRIS',
                'format' => 1,
                'created_at' => now()->toIso8601String(),
                'label' => $label,
                'agency' => Setting::get('agency_name'),
                'driver' => DB::connection()->getDriverName(),
                'key_check' => $this->keyCheck(),
                'migrations' => DB::table('migrations')->orderBy('id')->pluck('migration')->all(),
                'tables' => $counts,
                'files' => $files,
                'snapshots' => $snapshots,
            ], JSON_PRETTY_PRINT));
            $zip->close();
        } finally {
            File::deleteDirectory($work);
        }

        return $name;
    }

    /** @return Collection<int, array{name: string, size: int, date: CarbonInterface}> */
    public function list(): Collection
    {
        $disk = Storage::disk('local');

        return collect($disk->files(self::DIR))
            ->filter(fn ($f) => str_ends_with($f, '.zip'))
            ->map(fn ($f) => ['name' => basename($f), 'size' => $disk->size($f), 'date' => now()->setTimestamp($disk->lastModified($f))])
            ->sortByDesc('date')->values();
    }

    public function path(string $name): string
    {
        abort_unless(preg_match('/^[A-Za-z0-9._-]+\.zip$/', $name) && Storage::disk('local')->exists(self::DIR.'/'.$name), 404);

        return Storage::disk('local')->path(self::DIR.'/'.$name);
    }

    public function delete(string $name): void
    {
        $this->path($name);
        Storage::disk('local')->delete(self::DIR.'/'.$name);
    }

    /** Keeps the newest $keep backups and deletes the rest. Returns how many were deleted. */
    public function prune(int $keep): int
    {
        $old = $this->list()->slice(max(1, $keep));
        $old->each(fn ($b) => Storage::disk('local')->delete(self::DIR.'/'.$b['name']));

        return $old->count();
    }

    /**
     * Reads and checks a backup's manifest without changing anything.
     *
     * @return array<string, mixed>
     */
    public function inspect(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('This is not a valid backup file.');
        }
        $manifest = json_decode((string) $zip->getFromName('manifest.json'), true);
        $zip->close();

        if (! is_array($manifest) || ($manifest['app'] ?? null) !== 'HRIS') {
            throw new RuntimeException('This file is not an HRIS backup.');
        }
        if (($manifest['key_check'] ?? null) !== $this->keyCheck()) {
            throw new RuntimeException('This backup was made with a different APP_KEY. Encrypted data (government IDs, 2FA) could not be read. Restore it on the original server, or set the original APP_KEY first.');
        }
        $ran = DB::table('migrations')->pluck('migration')->all();
        $newer = array_diff($manifest['migrations'] ?? [], $ran);
        if ($newer) {
            throw new RuntimeException('This backup is from a newer version of the HRIS. Update this installation first, then restore.');
        }

        return $manifest;
    }

    /**
     * Replaces all data with the backup's. Makes a safety backup first and returns its name.
     * Files in the backup are written back; files added since are left in place.
     */
    public function restore(string $path): string
    {
        $manifest = $this->inspect($path);
        $safety = $this->create('before-restore');

        $zip = new ZipArchive;
        $zip->open($path);
        $tables = array_values(array_intersect($this->inDependencyOrder($this->tables()), array_keys($manifest['tables'])));

        Schema::disableForeignKeyConstraints();
        try {
            DB::transaction(function () use ($zip, $tables) {
                foreach (array_reverse($tables) as $table) {
                    DB::table($table)->delete();
                }
                foreach ($tables as $table) {
                    $stream = $zip->getStream("database/{$table}.jsonl");
                    if (! $stream) {
                        continue;
                    }
                    $batch = [];
                    while (($line = fgets($stream)) !== false) {
                        if (trim($line) === '') {
                            continue;
                        }
                        $batch[] = json_decode($line, true);
                        if (count($batch) >= 200) {
                            DB::table($table)->insert($batch);
                            $batch = [];
                        }
                    }
                    if ($batch) {
                        DB::table($table)->insert($batch);
                    }
                    fclose($stream);
                }
            });
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->getNameIndex($i);
            if (! preg_match('#^files/(public|local)/(.+)$#', $entry, $m) || str_contains($m[2], '..')) {
                continue;
            }
            Storage::disk($m[1])->put($m[2], $zip->getFromIndex($i));
        }
        $zip->close();

        Cache::forget('dtr.settings');
        Cache::forget('hris.installed');

        return $safety;
    }

    /**
     * Parents before children (employees before punches), so inserts never break a foreign key
     * even where the database can't switch the checks off inside a transaction.
     *
     * @param  list<string>  $tables
     * @return list<string>
     */
    private function inDependencyOrder(array $tables): array
    {
        $parents = [];
        foreach ($tables as $table) {
            $parents[$table] = collect(Schema::getForeignKeys($table))->pluck('foreign_table')
                ->map(fn ($t) => Str::afterLast($t, '.'))->filter(fn ($t) => $t !== $table && in_array($t, $tables, true))->unique()->values()->all();
        }
        $ordered = [];
        while ($parents) {
            $ready = array_keys(array_filter($parents, fn ($p) => ! array_diff($p, $ordered)));
            if (! $ready) {
                $ready = array_keys($parents); // circular references: fall back to any order
            }
            foreach ($ready as $table) {
                $ordered[] = $table;
                unset($parents[$table]);
            }
        }

        return $ordered;
    }

    /** Short fingerprint of APP_KEY, so a backup is never restored where its encrypted data can't be read. */
    private function keyCheck(): string
    {
        return substr(hash('sha256', 'hris-backup|'.config('app.key')), 0, 16);
    }
}
