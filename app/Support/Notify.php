<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\HrisNotice;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Sends HRIS notices: in the HRIS (bell icon) for anyone with a login, and by email when email
 * notifications are on. Never breaks the action that triggered it: a failure is logged.
 */
class Notify
{
    public static function employee(?Employee $employee, string $subject, array $lines, ?string $url = null): void
    {
        if (! $employee) {
            return;
        }
        if ($employee->user && ! $employee->user->disabled_at) {
            self::user($employee->user, $subject, $lines, $url);
        } elseif ($employee->email) {
            self::email($employee->email, $subject, $lines, $url);
        }
    }

    /** The employee's supervisor, or HR when there is none. */
    public static function approver(Employee $employee, string $subject, array $lines, ?string $url = null): void
    {
        $supervisor = $employee->supervisor;
        if ($supervisor && ($supervisor->user || $supervisor->email)) {
            self::employee($supervisor, $subject, $lines, $url ?? route('me.approvals'));
        } else {
            self::hr($subject, $lines);
        }
    }

    /**
     * HR: in the HRIS for HR officers and administrators (or those who manage $area), by email to the
     * HR address in Settings.
     */
    public static function hr(string $subject, array $lines, ?string $url = null, ?string $area = null): void
    {
        $url ??= route('admin.dashboard');
        $staff = User::whereIn('role', array_keys(array_filter(config('hris.roles'), fn ($r) => $r['manage'] !== [])))->whereNull('disabled_at')->get()
            ->filter(fn (User $u) => $area ? $u->canManage($area) : in_array($u->role, ['admin', 'hr'], true));
        foreach ($staff as $user) {
            self::user($user, $subject, $lines, $url, false);
        }
        foreach (array_filter(array_map('trim', explode(',', (string) Setting::get('hr_email')))) as $email) {
            self::email($email, $subject, $lines, $url);
        }
    }

    public static function user(User $user, string $subject, array $lines, ?string $url = null, bool $email = true): void
    {
        try {
            $user->notify(new HrisNotice($subject, $lines, $url, email: $email));
        } catch (Throwable $e) {
            Log::warning('HRIS notice failed', ['user' => $user->id, 'subject' => $subject, 'error' => $e->getMessage()]);
        }
    }

    /** Someone without a login (e.g. a job applicant), by email only. */
    public static function address(string $email, string $subject, array $lines, ?string $url = null): void
    {
        self::email($email, $subject, $lines, $url);
    }

    private static function email(string $email, string $subject, array $lines, ?string $url): void
    {
        if (Setting::get('notifications_enabled') !== '1') {
            return;
        }
        try {
            Notification::route('mail', $email)->notify(new HrisNotice($subject, $lines, $url));
        } catch (Throwable $e) {
            Log::warning('HRIS email failed', ['to' => $email, 'subject' => $subject, 'error' => $e->getMessage()]);
        }
    }
}
