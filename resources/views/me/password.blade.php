@extends('layouts.app')
@section('title', 'Change password')
@section('content')
<div class="card narrow">
    <h1>Change password</h1>
    <form method="post" action="{{ route('me.password') }}">
        @csrf @method('put')
        <div class="field"><label for="current_password">Current password</label><input type="password" id="current_password" name="current_password" autocomplete="current-password" required></div>
        <div class="field"><label for="password">New password</label><input type="password" id="password" name="password" autocomplete="new-password" required>
            <div class="hint">At least {{ \App\Models\Setting::get('password_min_length', '10') }} characters, with letters and numbers.</div></div>
        <div class="field"><label for="password_confirmation">Confirm new password</label><input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" required></div>
        <button class="btn btn-primary">Save</button>
    </form>
    <p class="small muted" style="margin:16px 0 0">Want extra protection? <a href="{{ route('account.security') }}">Turn on two-step verification</a>.</p>
</div>
@endsection
