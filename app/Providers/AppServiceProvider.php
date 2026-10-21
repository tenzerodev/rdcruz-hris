<?php

namespace App\Providers;

use App\Models\Announcement;
use App\Models\AnnouncementRead;
use App\Models\LeaveApplication;
use App\Models\OvertimeAuthorization;
use App\Models\PassSlip;
use App\Models\Setting;
use App\Models\TravelOrder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Password::defaults(fn () => Password::min(max(8, (int) Setting::get('password_min_length', '10')))->letters()->numbers());

        $this->useMailSettings();

        // Pending-item counts for the side navigation badges.
        View::composer('layouts.app', function ($view) {
            $user = auth()->user();
            if (! $user) {
                return $view->with('navCounts', []);
            }
            $counts = [];
            if ($user->isStaff()) {
                $counts = [
                    'leave' => $user->canManage('leave') ? LeaveApplication::where('status', 'recommended')->count() : 0,
                    'overtime' => $user->canManage('overtime') ? OvertimeAuthorization::where('status', 'pending')->count() : 0,
                    'travel' => $user->canManage('travel') ? TravelOrder::where('status', 'pending')->count() : 0,
                    'pass_slips' => $user->canManage('attendance') ? PassSlip::where('status', 'pending')->whereHas('employee', fn ($q) => $q->whereNull('supervisor_id'))->count() : 0,
                ];
            }
            $ids = $user->employee?->subordinates()->pluck('id') ?? collect();
            $counts['approvals'] = $ids->isEmpty() ? 0 :
                LeaveApplication::whereIn('employee_id', $ids)->where('status', 'pending')->count()
                + OvertimeAuthorization::whereIn('employee_id', $ids)->where('status', 'pending')->count()
                + TravelOrder::where('status', 'pending')->whereHas('employees', fn ($q) => $q->whereIn('employees.id', $ids))->count()
                + PassSlip::whereIn('employee_id', $ids)->where('status', 'pending')->count();

            $counts['unread'] = $user->unreadNotifications()->count();
            if ($user->employee) {
                $acked = AnnouncementRead::where('employee_id', $user->employee->id)->whereNotNull('acknowledged_at')->pluck('announcement_id');
                $counts['memos'] = Announcement::forEmployee($user->employee)->filter(fn ($a) => $a->requires_ack && ! $acked->contains($a->id))->count();
            }

            return $view->with('navCounts', $counts);
        });
    }

    /** SMTP settings entered on System → Email override .env, so IT doesn't need to edit files. */
    private function useMailSettings(): void
    {
        try {
            $s = Setting::allValues();
        } catch (Throwable) {
            return; // Not migrated yet.
        }
        if (empty($s['mail_host'])) {
            return;
        }
        $password = '';
        if (! empty($s['mail_password'])) {
            try {
                $password = Crypt::decryptString($s['mail_password']);
            } catch (Throwable) {
                $password = '';
            }
        }
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => $s['mail_host'],
            'mail.mailers.smtp.port' => (int) $s['mail_port'],
            'mail.mailers.smtp.scheme' => $s['mail_encryption'] === 'ssl' ? 'smtps' : 'smtp',
            'mail.mailers.smtp.username' => $s['mail_username'] ?: null,
            'mail.mailers.smtp.password' => $password ?: null,
            'mail.from.address' => $s['mail_from_address'] ?: config('mail.from.address'),
            'mail.from.name' => $s['mail_from_name'] ?: ($s['agency_name'] ?? config('mail.from.name')),
        ]);
    }
}
