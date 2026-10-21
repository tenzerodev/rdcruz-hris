<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use App\Support\DeviceCookie;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs on every signed-in web request: disabled accounts, idle timeout,
 * forced password change and required two-step verification for staff.
 */
class EnforceAccountSecurity
{
    /** Pages that stay reachable while a password change or 2FA setup is pending. */
    private const ALLOWED = ['account.*', 'logout', 'privacy'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        if ($user->disabled_at) {
            return $this->logout($request, 'This account has been disabled. Please contact HR.');
        }

        $employee = $user->employee;
        if ($employee?->login_device_only && ! DeviceCookie::deviceFor($request, $employee)) {
            return $this->logout($request, 'Your account can only be used on your agency-issued computer. Sign in from that computer.');
        }

        $idle = (int) Setting::get('session_idle_minutes', '30');
        $last = (int) $request->session()->get('last_activity', 0);
        $now = now()->getTimestamp();
        if ($idle > 0 && $last > 0 && $now - $last > $idle * 60) {
            return $this->logout($request, "You were signed out after {$idle} minutes without activity.");
        }
        $request->session()->put('last_activity', $now);

        if ($request->routeIs(...self::ALLOWED)) {
            return $next($request);
        }

        if ($user->must_change_password) {
            return $this->redirect($request, 'Please choose a new password before continuing.');
        }

        if ($user->isStaff() && ! $user->hasTwoFactor() && Setting::get('require_2fa_staff') === '1') {
            return $this->redirect($request, 'Your agency requires two-step verification for HR accounts. Please set it up to continue.');
        }

        return $next($request);
    }

    private function redirect(Request $request, string $message): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['ok' => false, 'message' => $message], 403);
        }

        return redirect()->route('account.security')->with('warning', $message);
    }

    private function logout(Request $request, string $message): Response
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            return response()->json(['ok' => false, 'message' => $message], 401);
        }

        return redirect()->route('login')->withErrors(['username' => $message]);
    }
}
