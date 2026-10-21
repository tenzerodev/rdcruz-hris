@extends('layouts.app')
@section('title', 'Sign-in & security')
@section('content')
<div class="page-head">
    <div><h1>Sign-in &amp; security</h1><div class="muted">Your password and two-step verification. Signed in as <b>{{ $user->username }}</b> · {{ $user->roleLabel() }}</div></div>
</div>
<div class="grid grid-2">
    <section class="card">
        <h2>Password</h2>
        @if ($user->must_change_password)
            <p class="alert alert-warn"><x-icon name="alert" />Your password was set by HR. Choose your own to continue.</p>
        @elseif ($user->password_changed_at)
            <p class="small muted">Last changed {{ $user->password_changed_at->diffForHumans() }}.</p>
        @endif
        <form method="post" action="{{ route('account.password') }}">
            @csrf @method('put')
            <div class="field"><label for="current_password">Current password</label><input type="password" id="current_password" name="current_password" autocomplete="current-password" required></div>
            <div class="field"><label for="password">New password</label><input type="password" id="password" name="password" autocomplete="new-password" required>
                <div class="hint">At least {{ \App\Models\Setting::get('password_min_length', '10') }} characters, with letters and numbers. A short sentence is easy to remember and hard to guess.</div></div>
            <div class="field"><label for="password_confirmation">Confirm new password</label><input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" required></div>
            <button class="btn btn-primary">Change password</button>
        </form>
    </section>

    <section class="card">
        <h2>Two-step verification</h2>
        @if ($recovery)
            <div class="alert alert-warn"><x-icon name="alert" /><div><b>Save these recovery codes now.</b> They are shown only once. Each one lets you sign in once if you lose your phone.</div></div>
            <pre class="codes" data-codes>{{ implode("\n", $recovery) }}</pre>
            <div class="actions" style="justify-content:flex-start;margin-bottom:16px">
                <button type="button" class="btn btn-sm" onclick="navigator.clipboard && navigator.clipboard.writeText(document.querySelector('[data-codes]').textContent)">Copy</button>
                <button type="button" class="btn btn-sm" onclick="window.print()">Print</button>
            </div>
        @endif

        @if ($user->hasTwoFactor())
            <p><span class="badge badge-ok">On</span> since {{ $user->two_factor_confirmed_at->format('M j, Y') }} · {{ count($user->two_factor_recovery_codes ?? []) }} recovery code(s) left</p>
            <p class="small muted">When you sign in, you'll type your password and then a 6-digit code from your authenticator app.</p>
            <form method="post" action="{{ route('account.2fa.recovery') }}" class="inline-form" style="margin-bottom:10px">
                @csrf
                <div class="field"><label for="pw-rc" class="sr">Password</label><input type="password" id="pw-rc" name="password" placeholder="Your password" required autocomplete="current-password"></div>
                <button class="btn btn-sm">Make new recovery codes</button>
            </form>
            @unless ($required)
                <form method="post" action="{{ route('account.2fa.disable') }}" class="inline-form" onsubmit="return confirm('Turn off two-step verification?')">
                    @csrf @method('delete')
                    <div class="field"><label for="pw-off" class="sr">Password</label><input type="password" id="pw-off" name="password" placeholder="Your password" required autocomplete="current-password"></div>
                    <button class="btn btn-sm btn-danger">Turn off</button>
                </form>
            @endunless
        @elseif ($pending)
            <ol class="steps">
                <li>Install an authenticator app on your phone (Google Authenticator, Microsoft Authenticator or Authy).</li>
                <li>In the app, add an account and scan this code.
                    <div class="qr-box">{!! $qr !!}</div>
                    <div class="small muted">Can't scan? Enter this key: <code style="word-break:break-all">{{ trim(chunk_split($pending, 4, ' ')) }}</code></div></li>
                <li>Type the 6-digit code the app shows.</li>
            </ol>
            <form method="post" action="{{ route('account.2fa.confirm') }}" class="inline-form">
                @csrf
                <div class="field"><label for="code" class="sr">Code</label><input type="text" id="code" name="code" inputmode="numeric" maxlength="7" autocomplete="one-time-code" placeholder="123456" required autofocus style="width:140px;letter-spacing:.2em"></div>
                <button class="btn btn-primary">Turn on</button>
            </form>
            <form method="post" action="{{ route('account.2fa.cancel') }}" style="margin-top:8px">@csrf<button class="link-btn small">Cancel</button></form>
        @else
            <p><span class="badge {{ $required ? 'badge-error' : '' }}">Off</span> @if ($required)<b>Required for your account.</b>@endif</p>
            <p class="small muted">Adds a second step when you sign in: a 6-digit code from an app on your phone. Someone who learns your password still can't get in.</p>
            <form method="post" action="{{ route('account.2fa.enable') }}" class="inline-form">
                @csrf
                <div class="field"><label for="pw-on" class="sr">Password</label><input type="password" id="pw-on" name="password" placeholder="Your password" required autocomplete="current-password"></div>
                <button class="btn btn-primary">Set up</button>
            </form>
        @endif
    </section>
</div>
@if ($user->last_login_at)
    <p class="small muted" style="margin-top:16px">Last sign-in: {{ $user->last_login_at->format('M j, Y g:i A') }} from {{ $user->last_login_ip }}. If that wasn't you, change your password and tell HR.</p>
@endif
@endsection
