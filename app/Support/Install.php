<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Throwable;

/** First-run detection: the setup wizard is open only until a system administrator exists. */
class Install
{
    public static function needed(): bool
    {
        try {
            if (Cache::get('hris.installed')) {
                return false;
            }
            $installed = User::where('role', 'admin')->exists();
        } catch (Throwable) {
            return false; // Database not migrated yet; the deployment guide covers that step.
        }

        if ($installed) {
            Cache::forever('hris.installed', true);
        }

        return ! $installed;
    }
}
