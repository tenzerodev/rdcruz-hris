<?php

namespace App\Models;

use App\Support\Agencies;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    public static function allValues(): array
    {
        // Cache only stored values; defaults are merged on read so newly added settings always apply.
        $stored = Cache::rememberForever('dtr.settings', fn () => static::query()->pluck('value', 'key')->all());

        return array_merge(config('dtr.defaults'), $stored);
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        return static::allValues()[$key] ?? $default;
    }

    /** Public URL of the uploaded agency logo, or null. */
    public static function logoUrl(): ?string
    {
        $path = static::get('logo_path');

        if (! $path) {
            return null;
        }

        return ! empty(Agencies::current()['own_storage']) ? url('agency-files/'.$path) : asset('storage/'.$path);
    }

    public static function put(array $values): void
    {
        foreach ($values as $key => $value) {
            static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }
        Cache::forget('dtr.settings');
    }
}
