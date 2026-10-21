@extends('layouts.app')
@section('title', 'Log in')
@section('bare', true)
@section('main-class', 'auth')
@section('content')
<div class="card">
    @php $logo = \App\Models\Setting::logoUrl(); @endphp
    <div class="brand"><span class="logo" aria-hidden="true">@if ($logo)<img src="{{ $logo }}" alt="">@else HR @endif</span><span><b>HRIS</b><small>{{ \App\Models\Setting::get('agency_name') }}</small></span></div>
    <h1>Sign in</h1>
    <p class="muted">Use the username and password from HR.</p>
    <form method="post" action="{{ url('/login') }}">
        @csrf
        <div class="field">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" value="{{ old('username') }}" autocomplete="username" required autofocus>
        </div>
        <div class="field">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" autocomplete="current-password" required>
        </div>
        <div class="field"><label class="check"><input type="checkbox" name="remember" value="1"> Keep me logged in on this device</label></div>
        <button class="btn btn-primary" style="width:100%">Log in</button>
    </form>
    <p class="small muted" style="margin:16px 0 0;text-align:center">Have a printed document? <a href="{{ route('verify.form') }}">Verify it here</a> · <a href="{{ route('privacy') }}">Privacy notice</a></p>
</div>
@endsection
