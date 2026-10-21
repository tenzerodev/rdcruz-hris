<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Notifications\HrisNotice;
use App\Services\SystemHealth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Notification;
use Throwable;

/** System status plus the settings IT looks after: security, email, privacy and backups. */
class SystemController extends Controller
{
    public function index(SystemHealth $health)
    {
        $checks = $health->checks();

        return view('admin.system', [
            'checks' => $checks,
            'summary' => $health->summary($checks),
            's' => Setting::allValues(),
        ]);
    }

    public function security(Request $request)
    {
        $data = $request->validate([
            'session_idle_minutes' => ['required', 'integer', 'min:0', 'max:720'],
            'lockout_attempts' => ['required', 'integer', 'min:3', 'max:20'],
            'lockout_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'password_min_length' => ['required', 'integer', 'min:8', 'max:64'],
        ]);
        $data['require_2fa_staff'] = $request->boolean('require_2fa_staff') ? '1' : '0';
        Setting::put(array_map('strval', $data));
        AuditLog::record('system.security_updated', null, $data);

        return back()->with('status', 'Security settings saved.');
    }

    public function mail(Request $request)
    {
        $data = $request->validate([
            'mail_host' => ['nullable', 'string', 'max:150'],
            'mail_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'mail_encryption' => ['required', 'in:tls,ssl,none'],
            'mail_username' => ['nullable', 'string', 'max:150'],
            'mail_password' => ['nullable', 'string', 'max:300'],
            'mail_from_address' => ['nullable', 'required_with:mail_host', 'email', 'max:150'],
            'mail_from_name' => ['nullable', 'string', 'max:150'],
        ]);
        // A blank password keeps the saved one; it's stored encrypted.
        if (filled($data['mail_password'] ?? null)) {
            $data['mail_password'] = Crypt::encryptString($data['mail_password']);
        } else {
            unset($data['mail_password']);
        }
        if ($request->boolean('clear_password')) {
            $data['mail_password'] = '';
        }
        Setting::put(array_map(fn ($v) => (string) $v, $data));
        AuditLog::record('system.mail_updated', null, ['host' => $data['mail_host'] ?? '']);

        return back()->with('status', 'Email settings saved. Send a test to make sure they work.');
    }

    public function testMail(Request $request)
    {
        $data = $request->validate(['to' => ['required', 'email']]);
        try {
            Notification::route('mail', $data['to'])->notifyNow(new HrisNotice('HRIS test email', [
                'This is a test from the HRIS of '.Setting::get('agency_name').'.',
                'If you received it, email notifications are working.',
            ], route('admin.dashboard')));
        } catch (Throwable $e) {
            return back()->withErrors(['to' => 'Sending failed: '.$e->getMessage()]);
        }

        return back()->with('status', "Test email sent to {$data['to']}. If it doesn't arrive in a few minutes, check the spam folder and the settings.");
    }

    public function privacy(Request $request)
    {
        $data = $request->validate([
            'dpo_name' => ['nullable', 'string', 'max:150'],
            'dpo_email' => ['nullable', 'email', 'max:150'],
            'dpo_phone' => ['nullable', 'string', 'max:50'],
            'privacy_extra' => ['nullable', 'string', 'max:5000'],
            'face_purge_days' => ['required', 'integer', 'min:0', 'max:3650'],
            'snapshot_retention_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
        ]);
        Setting::put(array_map(fn ($v) => (string) $v, $data));
        AuditLog::record('system.privacy_updated');

        return back()->with('status', 'Privacy settings saved. The privacy notice is updated.');
    }

    public function backups(Request $request)
    {
        $data = $request->validate(['backup_keep' => ['required', 'integer', 'min:1', 'max:365']]);
        Setting::put([
            'backup_keep' => (string) $data['backup_keep'],
            'backup_enabled' => $request->boolean('backup_enabled') ? '1' : '0',
            'backup_include_snapshots' => $request->boolean('backup_include_snapshots') ? '1' : '0',
        ]);
        AuditLog::record('system.backup_settings_updated');

        return back()->with('status', 'Backup settings saved.');
    }
}
