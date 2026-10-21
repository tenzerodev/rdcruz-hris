<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\IssuedDevice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Cookie as HttpCookie;

/**
 * Remembers an agency-issued computer in its browser: an encrypted, long-lived cookie holding
 * "device id|secret". Only a hash of the secret is stored, like kiosk activation.
 */
class DeviceCookie
{
    public const NAME = 'hris_device';

    public static function make(IssuedDevice $device, string $token): HttpCookie
    {
        return Cookie::make(self::NAME, $device->id.'|'.$token, 60 * 24 * 365 * 5, null, null, null, true, false, 'lax');
    }

    /** The registered device this browser belongs to, if valid and still active. */
    public static function device(Request $request): ?IssuedDevice
    {
        $value = (string) $request->cookie(self::NAME);
        if (! str_contains($value, '|')) {
            return null;
        }
        [$id, $token] = explode('|', $value, 2);
        $device = IssuedDevice::where('is_active', true)->find((int) $id);
        if (! $device || ! $device->token_hash || ! hash_equals($device->token_hash, hash('sha256', $token))) {
            return null;
        }

        return $device;
    }

    /** The registered device, but only if it was issued to this employee. */
    public static function deviceFor(Request $request, Employee $employee): ?IssuedDevice
    {
        $device = self::device($request);

        return $device && $device->employee_id === $employee->id ? $device : null;
    }
}
