<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use RuntimeException;

/**
 * Several agencies on one server (optional, HRIS_MULTI_AGENCY=true). Each agency has its own
 * database, files, cache and sessions, and is picked by the web address (host) it is opened at.
 *
 * The list lives in storage/app/agencies.json and is managed with the hris:agency:* commands.
 * Entry: {id, name, hosts: [], database, own_storage: bool}.
 */
class Agencies
{
    private static ?array $current = null;

    public static function enabled(): bool
    {
        return (bool) config('hris.multi_agency.enabled');
    }

    /** @return array<string, array{id: string, name: string, hosts: list<string>, database: string, own_storage: bool}> */
    public static function all(): array
    {
        $file = config('hris.multi_agency.registry');
        $list = is_file($file) ? json_decode((string) file_get_contents($file), true) : [];

        return is_array($list) ? $list : [];
    }

    public static function save(array $agencies): void
    {
        $file = config('hris.multi_agency.registry');
        File::ensureDirectoryExists(dirname($file));
        file_put_contents($file, json_encode($agencies, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    }

    public static function find(string $id): ?array
    {
        return self::all()[$id] ?? null;
    }

    public static function forHost(string $host): ?array
    {
        $host = strtolower($host);
        foreach (self::all() as $agency) {
            if (in_array($host, array_map('strtolower', $agency['hosts']), true)) {
                return $agency;
            }
        }

        return null;
    }

    /** Forgets the active agency (between tests, or long-running workers). */
    public static function reset(): void
    {
        self::$current = null;
        URL::forceRootUrl(null);
    }

    public static function current(): ?array
    {
        return self::$current;
    }

    /** Points the database, files, cache and session cookie at this agency. */
    public static function activate(array $agency): void
    {
        if (! preg_match('/^[a-z0-9_-]{2,40}$/', $agency['id'] ?? '')) {
            throw new RuntimeException('Invalid agency id.');
        }
        $connection = config('database.default');
        config(["database.connections.$connection.database" => $agency['database']]);
        DB::purge($connection);

        if (! empty($agency['own_storage'])) {
            $root = storage_path('app/agencies/'.$agency['id']);
            config([
                'filesystems.disks.local.root' => "$root/private",
                'filesystems.disks.public.root' => "$root/public",
                'filesystems.disks.public.url' => url('agency-files'),
            ]);
            Storage::forgetDisk(['local', 'public']);
        }

        config([
            'cache.prefix' => 'hris_'.$agency['id'].'_',
            'cache.stores.file.path' => storage_path('framework/cache/data/'.$agency['id']),
            'session.cookie' => 'hris_'.$agency['id'].'_session',
            'session.files' => storage_path('framework/sessions/'.$agency['id']),
        ]);
        File::ensureDirectoryExists(config('session.files'));
        Cache::forgetDriver();
        app()->forgetInstance('cache.store');

        // Links in emails and reminders sent by scheduled tasks point at the agency's own address.
        if (app()->runningInConsole() && ! empty($agency['hosts'][0])) {
            URL::forceRootUrl((parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'https').'://'.$agency['hosts'][0]);
        }

        self::$current = $agency;
    }

    /** Runs $callback once for every agency (commands and scheduled tasks). */
    public static function each(callable $callback): void
    {
        foreach (self::all() as $agency) {
            self::activate($agency);
            $callback($agency);
        }
    }
}
