@extends('layouts.app')
@section('title', 'Two-step verification')
@section('bare', true)
@section('main-class', 'auth')
@section('content')
<div class="card">
    <h1>Two-step verification</h1>
    <div data-mode="app">
        <p class="muted">Open your authenticator app and type the 6-digit code for this account.</p>
        <form method="post" action="{{ route('login.two-factor') }}">
            @csrf
            <div class="field"><label for="code">Code</label>
                <input type="text" id="code" name="code" inputmode="numeric" pattern="[0-9 ]*" maxlength="7" autocomplete="one-time-code" required autofocus style="font-size:1.4rem;letter-spacing:.3em;text-align:center"></div>
            <button class="btn btn-primary" style="width:100%">Verify</button>
        </form>
        <p class="small" style="margin:14px 0 0;text-align:center"><button type="button" class="link-btn" data-switch="recovery">Lost your phone? Use a recovery code</button></p>
    </div>
    <div data-mode="recovery" hidden>
        <p class="muted">Type one of the recovery codes you saved when you turned on two-step verification. Each code works once.</p>
        <form method="post" action="{{ route('login.two-factor') }}">
            @csrf
            <div class="field"><label for="recovery_code">Recovery code</label><input type="text" id="recovery_code" name="recovery_code" autocomplete="off" placeholder="xxxxx-xxxxx"></div>
            <button class="btn btn-primary" style="width:100%">Verify</button>
        </form>
        <p class="small" style="margin:14px 0 0;text-align:center"><button type="button" class="link-btn" data-switch="app">Use the authenticator app instead</button></p>
    </div>
    <p class="small muted" style="margin:16px 0 0;text-align:center">No phone and no recovery codes? Ask the system administrator to reset two-step verification on your account. <a href="{{ route('login') }}">Back to sign in</a></p>
</div>
<script>
document.querySelectorAll('[data-switch]').forEach(function (b) {
  b.addEventListener('click', function () {
    document.querySelectorAll('[data-mode]').forEach(function (m) { m.hidden = m.dataset.mode !== b.dataset.switch; });
    var input = document.querySelector('[data-mode="' + b.dataset.switch + '"] input:not([type=hidden])');
    document.getElementById('code').required = b.dataset.switch === 'app';
    if (input) input.focus();
  });
});
</script>
@endsection
