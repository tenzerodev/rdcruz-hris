@extends('layouts.app')
@section('title', 'System status')
@section('content')
<div class="page-head">
    <div><h1>System status</h1><div class="muted">What IT should check before and after going live. The same checks run with <code>php artisan hris:check</code>.</div></div>
    <div class="actions"><a class="btn" href="{{ route('admin.backups.index') }}">Backups</a><a class="btn" href="{{ route('admin.users.index') }}">User accounts</a></div>
</div>

<section class="card" style="margin-bottom:16px">
    <div class="card-head"><h2>Health checks</h2>
        <div class="small">
            <span class="badge badge-ok">{{ $summary['ok'] }} OK</span>
            @if ($summary['warn'])<span class="badge badge-warn">{{ $summary['warn'] }} to improve</span>@endif
            @if ($summary['fail'])<span class="badge badge-error">{{ $summary['fail'] }} to fix</span>@endif
        </div>
    </div>
    <ul class="checks">
        @foreach ($checks as $c)
            <li class="{{ $c['status'] }}">
                <span class="dot" aria-hidden="true"></span>
                <div><b>{{ $c['label'] }}</b> <span class="sr">({{ $c['status'] }})</span><div class="small">{{ $c['detail'] }}</div>@if ($c['fix'])<div class="fix">{{ $c['fix'] }}</div>@endif</div>
                <span class="badge {{ ['ok' => 'badge-ok', 'warn' => 'badge-warn', 'fail' => 'badge-error'][$c['status']] }}">{{ ['ok' => 'OK', 'warn' => 'Improve', 'fail' => 'Fix'][$c['status']] }}</span>
            </li>
        @endforeach
    </ul>
</section>

<div class="grid grid-2">
    <section class="card">
        <h2>Sign-in security</h2>
        <form method="post" action="{{ route('admin.system.security') }}">
            @csrf @method('put')
            <label class="check"><input type="checkbox" name="require_2fa_staff" value="1" @checked($s['require_2fa_staff'] === '1')>
                <span>Require two-step verification for HR accounts<span class="hint" style="display:block">HR, records, payroll, timekeepers, auditors and administrators must use an authenticator app. They're asked to set it up at their next sign-in. Employees can turn it on themselves.</span></span></label>
            <div class="row">
                <div class="field"><label for="session_idle_minutes">Sign out after idle (minutes)</label><input type="number" id="session_idle_minutes" name="session_idle_minutes" min="0" max="720" value="{{ $s['session_idle_minutes'] }}" required><div class="hint">0 = never</div></div>
                <div class="field"><label for="password_min_length">Minimum password length</label><input type="number" id="password_min_length" name="password_min_length" min="8" max="64" value="{{ $s['password_min_length'] }}" required></div>
            </div>
            <div class="row">
                <div class="field"><label for="lockout_attempts">Lock after wrong passwords</label><input type="number" id="lockout_attempts" name="lockout_attempts" min="3" max="20" value="{{ $s['lockout_attempts'] }}" required></div>
                <div class="field"><label for="lockout_minutes">Lock for (minutes)</label><input type="number" id="lockout_minutes" name="lockout_minutes" min="1" max="1440" value="{{ $s['lockout_minutes'] }}" required></div>
            </div>
            <button class="btn btn-primary">Save security settings</button>
        </form>
    </section>

    <section class="card">
        <h2>Email</h2>
        <p class="small muted" style="margin-top:0">Used for leave, overtime and travel notifications, and contract reminders. Use your agency mail server, or an SMTP service. Leave the server empty to use the settings in <code>.env</code>.</p>
        <form method="post" action="{{ route('admin.system.mail') }}">
            @csrf @method('put')
            <div class="row">
                <div class="field" style="flex:2"><label for="mail_host">SMTP server</label><input type="text" id="mail_host" name="mail_host" value="{{ old('mail_host', $s['mail_host']) }}" placeholder="smtp.agency.gov.ph"></div>
                <div class="field"><label for="mail_port">Port</label><input type="number" id="mail_port" name="mail_port" value="{{ old('mail_port', $s['mail_port']) }}" required></div>
                <div class="field"><label for="mail_encryption">Security</label><select id="mail_encryption" name="mail_encryption">
                    @foreach (['tls' => 'STARTTLS (587)', 'ssl' => 'SSL/TLS (465)', 'none' => 'None'] as $k => $l)<option value="{{ $k }}" @selected($s['mail_encryption'] === $k)>{{ $l }}</option>@endforeach</select></div>
            </div>
            <div class="row">
                <div class="field"><label for="mail_username">Username</label><input type="text" id="mail_username" name="mail_username" value="{{ old('mail_username', $s['mail_username']) }}" autocomplete="off"></div>
                <div class="field"><label for="mail_password">Password</label><input type="password" id="mail_password" name="mail_password" autocomplete="new-password" placeholder="{{ $s['mail_password'] ? '•••••••• (saved)' : '' }}"><div class="hint">Leave blank to keep the saved one. Stored encrypted.</div></div>
            </div>
            <div class="row">
                <div class="field"><label for="mail_from_address">Send from (email)</label><input type="email" id="mail_from_address" name="mail_from_address" value="{{ old('mail_from_address', $s['mail_from_address']) }}" placeholder="hris@agency.gov.ph"></div>
                <div class="field"><label for="mail_from_name">Sender name</label><input type="text" id="mail_from_name" name="mail_from_name" value="{{ old('mail_from_name', $s['mail_from_name']) }}" placeholder="{{ $s['agency_name'] }} HRIS"></div>
            </div>
            <button class="btn btn-primary">Save email settings</button>
        </form>
        <form method="post" action="{{ route('admin.system.mail.test') }}" class="inline-form" style="margin-top:14px">
            @csrf
            <div class="field"><label for="to">Send a test email to</label><input type="email" id="to" name="to" value="{{ auth()->user()->email }}" required></div>
            <button class="btn">Send test</button>
        </form>
    </section>

    <section class="card">
        <h2>Data privacy (RA 10173)</h2>
        <p class="small muted" style="margin-top:0">Shown on the <a href="{{ route('privacy') }}" target="_blank">privacy notice</a>, which is linked from sign-in, kiosks and face enrollment.</p>
        <form method="post" action="{{ route('admin.system.privacy') }}">
            @csrf @method('put')
            <div class="row">
                <div class="field"><label for="dpo_name">Data Protection Officer</label><input type="text" id="dpo_name" name="dpo_name" value="{{ old('dpo_name', $s['dpo_name']) }}"></div>
                <div class="field"><label for="dpo_email">DPO email</label><input type="email" id="dpo_email" name="dpo_email" value="{{ old('dpo_email', $s['dpo_email']) }}"></div>
                <div class="field"><label for="dpo_phone">DPO phone</label><input type="text" id="dpo_phone" name="dpo_phone" value="{{ old('dpo_phone', $s['dpo_phone']) }}"></div>
            </div>
            <div class="row">
                <div class="field"><label for="snapshot_retention_days">Keep punch photos (days)</label><input type="number" id="snapshot_retention_days" name="snapshot_retention_days" min="1" max="3650" value="{{ old('snapshot_retention_days', $s['snapshot_retention_days'] ?? config('dtr.snapshot_retention_days')) }}"></div>
                <div class="field"><label for="face_purge_days">Delete face data after separation (days)</label><input type="number" id="face_purge_days" name="face_purge_days" min="0" max="3650" value="{{ old('face_purge_days', $s['face_purge_days']) }}" required><div class="hint">0 = right away (checked nightly)</div></div>
            </div>
            <div class="field"><label for="privacy_extra">Additional text for the privacy notice (optional)</label><textarea id="privacy_extra" name="privacy_extra" rows="4">{{ old('privacy_extra', $s['privacy_extra']) }}</textarea></div>
            <button class="btn btn-primary">Save privacy settings</button>
        </form>
    </section>
</div>
@endsection
