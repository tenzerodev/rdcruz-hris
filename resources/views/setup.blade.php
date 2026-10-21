@extends('layouts.app')
@section('title', 'Set up')
@section('bare', true)
@section('main-class', 'setup')
@section('content')
<h1>Welcome — let's set up your HRIS</h1>
<p class="muted">This takes about two minutes. You can change everything later in Settings. This page closes once the administrator account is made.</p>
<form method="post" action="{{ route('setup') }}" enctype="multipart/form-data">
    @csrf
    <fieldset>
        <legend>1. Your agency</legend>
        <div class="field"><label for="agency_name">Agency name</label><input type="text" id="agency_name" name="agency_name" value="{{ old('agency_name') }}" required placeholder="e.g. Center for International Trade Expositions and Missions">
            <div class="hint">Printed on the DTR, leave forms, certificates and every other document.</div></div>
        <div class="field"><label for="agency_address">Address</label><input type="text" id="agency_address" name="agency_address" value="{{ old('agency_address') }}"></div>
        <div class="field"><label for="logo">Logo (optional)</label><input type="file" id="logo" name="logo" accept="image/png,image/jpeg,image/webp">
            <div class="hint">PNG, JPG or WebP, up to 1 MB. A square image looks best.</div></div>
        <div class="field"><label for="hr_email">HR email for notifications (optional)</label><input type="email" id="hr_email" name="hr_email" value="{{ old('hr_email') }}" placeholder="hr@agency.gov.ph"></div>
        <label class="check"><input type="checkbox" name="holidays" value="1" @checked(old('holidays', true))> <span>Add the Philippine regular holidays for {{ $year }} and {{ $year + 1 }} (add special days and Eid holidays later on the Holidays page)</span></label>
    </fieldset>
    <fieldset>
        <legend>2. System administrator</legend>
        <p class="small muted" style="margin-top:0">This account manages user accounts, backups and settings. Make separate accounts for HR staff later, under System → User accounts.</p>
        <div class="row">
            <div class="field"><label for="name">Full name</label><input type="text" id="name" name="name" value="{{ old('name') }}" required autocomplete="name"></div>
            <div class="field"><label for="username">Username</label><input type="text" id="username" name="username" value="{{ old('username', 'admin') }}" required autocomplete="username"></div>
        </div>
        <div class="field"><label for="email">Email (optional)</label><input type="email" id="email" name="email" value="{{ old('email') }}" autocomplete="email"></div>
        <div class="row">
            <div class="field"><label for="password">Password</label><input type="password" id="password" name="password" required autocomplete="new-password">
                <div class="hint">At least {{ \App\Models\Setting::get('password_min_length', '10') }} characters, with letters and numbers.</div></div>
            <div class="field"><label for="password_confirmation">Confirm password</label><input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password"></div>
        </div>
    </fieldset>
    <button class="btn btn-primary">Finish setup</button>
</form>
@endsection
