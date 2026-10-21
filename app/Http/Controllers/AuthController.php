<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\IssuedDevice;
use App\Models\Setting;
use App\Models\User;
use App\Support\DeviceCookie;
use App\Support\Install;
use App\Support\TwoFactor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function show()
    {
        if (Install::needed()) {
            return redirect()->route('setup');
        }

        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string'],
        ]);

        $key = $this->throttleKey($request);
        $attempts = max(1, (int) Setting::get('lockout_attempts', '5'));
        if (RateLimiter::tooManyAttempts($key, $attempts)) {
            $minutes = (int) ceil(RateLimiter::availableIn($key) / 60);
            throw ValidationException::withMessages(['username' => "Too many wrong passwords. Try again in {$minutes} minute(s), or ask HR to reset your password."]);
        }

        $user = User::where('username', $credentials['username'])->first();
        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            RateLimiter::hit($key, max(1, (int) Setting::get('lockout_minutes', '15')) * 60);
            AuditLog::record('auth.failed', $user, ['username' => Str::limit($credentials['username'], 60)]);
            throw ValidationException::withMessages(['username' => 'Wrong username or password.']);
        }

        if ($user->disabled_at) {
            throw ValidationException::withMessages(['username' => 'This account has been disabled. Please contact HR.']);
        }
        if (! $user->isStaff() && ! $user->employee?->is_active) {
            throw ValidationException::withMessages(['username' => 'Your account is inactive.']);
        }

        RateLimiter::clear($key);

        // Accounts limited to issued computers: this browser must be a registered computer of theirs.
        $employee = $user->employee;
        if ($employee?->login_device_only && ! DeviceCookie::deviceFor($request, $employee)) {
            $request->session()->put('login.device', $user->id);
            $request->session()->put('login.remember', $request->boolean('remember'));
            AuditLog::record('auth.device_required', $user);

            return redirect()->route('login.device');
        }

        return $this->continueLogin($request, $user, $request->boolean('remember'));
    }

    /** Asks for HR's activation code to register this computer during sign-in. */
    public function deviceForm(Request $request)
    {
        if (! $request->session()->has('login.device')) {
            return redirect()->route('login');
        }

        return view('auth.device');
    }

    public function device(Request $request)
    {
        $user = User::find($request->session()->get('login.device'));
        if (! $user?->employee) {
            return redirect()->route('login');
        }
        $request->validate(['code' => ['required', 'string', 'max:20']]);

        $device = IssuedDevice::findByCode($request->input('code'));
        if (! $device || $device->employee_id !== $user->employee->id) {
            AuditLog::record('device.activation_failed', $user);
            throw ValidationException::withMessages(['code' => 'That code is not valid, has expired, or is for someone else. Ask HR for a new code.']);
        }
        $token = $device->activate($request->ip(), $request->userAgent());
        AuditLog::record('device.activated', $device, ['employee' => $user->employee->id, 'at' => 'sign-in']);
        $request->session()->forget('login.device');

        return $this->continueLogin($request, $user, (bool) $request->session()->get('login.remember'))
            ->withCookie(DeviceCookie::make($device, $token))
            ->with('status', "This computer is now registered as “{$device->name}”.");
    }

    /** Two-step verification if the account has it, otherwise straight in. */
    private function continueLogin(Request $request, User $user, bool $remember)
    {
        if ($user->hasTwoFactor()) {
            $request->session()->put('login.id', $user->id);
            $request->session()->put('login.remember', $remember);

            return redirect()->route('login.two-factor');
        }
        $request->session()->forget('login.remember');

        return $this->finish($request, $user, $remember);
    }

    public function twoFactorForm(Request $request)
    {
        if (! $request->session()->has('login.id')) {
            return redirect()->route('login');
        }

        return view('auth.two-factor');
    }

    public function twoFactor(Request $request)
    {
        $user = User::find($request->session()->get('login.id'));
        if (! $user) {
            return redirect()->route('login');
        }
        $request->validate(['code' => ['nullable', 'string', 'max:20'], 'recovery_code' => ['nullable', 'string', 'max:30']]);

        $ok = false;
        if ($request->filled('code')) {
            $ok = TwoFactor::verify($user->two_factor_secret, $request->input('code'), 'user-'.$user->id);
        } elseif ($request->filled('recovery_code')) {
            $codes = $user->two_factor_recovery_codes ?? [];
            $given = Str::lower(trim($request->input('recovery_code')));
            $match = collect($codes)->first(fn ($c) => hash_equals($c, $given));
            if ($match) {
                $user->forceFill(['two_factor_recovery_codes' => array_values(array_diff($codes, [$match]))])->save();
                AuditLog::record('auth.recovery_code_used', $user, ['remaining' => count($codes) - 1]);
                $ok = true;
            }
        }

        if (! $ok) {
            AuditLog::record('auth.2fa_failed', $user);
            throw ValidationException::withMessages(['code' => 'That code is not correct. Check the time on your phone, or use a recovery code.']);
        }

        $remember = (bool) $request->session()->pull('login.remember');
        $request->session()->forget('login.id');

        return $this->finish($request, $user, $remember);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }

    private function finish(Request $request, User $user, bool $remember)
    {
        Auth::login($user, $remember);
        $request->session()->regenerate();
        $request->session()->put('last_activity', now()->getTimestamp());
        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->saveQuietly();
        AuditLog::record('auth.login', $user);

        return redirect()->intended($user->homeUrl());
    }

    private function throttleKey(Request $request): string
    {
        return 'login:'.Str::lower((string) $request->input('username')).'|'.$request->ip();
    }
}
