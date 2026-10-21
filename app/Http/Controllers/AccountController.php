<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Setting;
use App\Support\TwoFactor;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/** Every user's own sign-in settings: password and two-step verification. */
class AccountController extends Controller
{
    public function security(Request $request)
    {
        $user = $request->user();
        $pending = $request->session()->get('2fa.pending');

        return view('account.security', [
            'user' => $user,
            'pending' => $pending,
            'qr' => $pending ? TwoFactor::qr(TwoFactor::uri($this->issuer(), $user->username, $pending)) : null,
            'recovery' => $request->session()->get('2fa.recovery'),
            'required' => $user->isStaff() && Setting::get('require_2fa_staff') === '1',
        ]);
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults(), 'different:current_password'],
        ], ['password.different' => 'Choose a password different from your current one.']);

        $request->user()->forceFill([
            'password' => $request->input('password'),
            'must_change_password' => false,
            'password_changed_at' => now(),
        ])->save();
        AuditLog::record('account.password_changed', $request->user());

        return redirect()->route('account.security')->with('status', 'Password changed.');
    }

    /** Step 1: make a secret and show the QR code; nothing is saved until a code is confirmed. */
    public function enableTwoFactor(Request $request)
    {
        $request->validate(['password' => ['required', 'current_password']]);
        $request->session()->put('2fa.pending', TwoFactor::generateSecret());

        return redirect()->route('account.security');
    }

    /** Step 2: the user types a code from the app, proving it was set up correctly. */
    public function confirmTwoFactor(Request $request)
    {
        $request->validate(['code' => ['required', 'string', 'max:10']]);
        $secret = $request->session()->get('2fa.pending');
        if (! $secret) {
            return redirect()->route('account.security');
        }
        if (! TwoFactor::verify($secret, $request->input('code'))) {
            throw ValidationException::withMessages(['code' => 'That code is not correct. Make sure the time on your phone is set automatically, then try the newest code.']);
        }

        $codes = TwoFactor::recoveryCodes();
        $request->user()->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => $codes,
            'two_factor_confirmed_at' => now(),
        ])->save();
        $request->session()->forget('2fa.pending');
        AuditLog::record('account.2fa_enabled', $request->user());

        return redirect()->route('account.security')->with('2fa.recovery', $codes)
            ->with('status', 'Two-step verification is on. Save your recovery codes now.');
    }

    public function cancelTwoFactor(Request $request)
    {
        $request->session()->forget('2fa.pending');

        return redirect()->route('account.security');
    }

    public function disableTwoFactor(Request $request)
    {
        $request->validate(['password' => ['required', 'current_password']]);
        $user = $request->user();
        if ($user->isStaff() && Setting::get('require_2fa_staff') === '1') {
            throw ValidationException::withMessages(['password' => 'Your agency requires two-step verification for HR accounts, so it cannot be turned off.']);
        }
        $user->forceFill(['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null])->save();
        AuditLog::record('account.2fa_disabled', $user);

        return redirect()->route('account.security')->with('status', 'Two-step verification is off.');
    }

    public function recoveryCodes(Request $request)
    {
        $request->validate(['password' => ['required', 'current_password']]);
        abort_unless($request->user()->hasTwoFactor(), 404);
        $codes = TwoFactor::recoveryCodes();
        $request->user()->forceFill(['two_factor_recovery_codes' => $codes])->save();
        AuditLog::record('account.recovery_codes_regenerated', $request->user());

        return redirect()->route('account.security')->with('2fa.recovery', $codes)
            ->with('status', 'New recovery codes made. The old ones no longer work.');
    }

    private function issuer(): string
    {
        return 'HRIS '.str((string) Setting::get('agency_name'))->limit(30, '');
    }
}
