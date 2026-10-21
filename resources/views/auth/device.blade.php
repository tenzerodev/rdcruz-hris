@extends('layouts.app')
@section('title', 'Use your issued computer')
@section('bare', true)
@section('main-class', 'auth')
@section('content')
<div class="card">
    <h1>Use your issued computer</h1>
    <p class="muted">Your account can only be used on your agency-issued computer, and this browser isn't registered yet.</p>
    <p class="small">If this <b>is</b> your issued computer, type the activation code from HR to register it. If not, sign in from your issued computer instead.</p>
    <form method="post" action="{{ route('login.device') }}">
        @csrf
        <div class="field"><label for="code">Activation code</label>
            <input type="text" id="code" name="code" autocomplete="off" placeholder="ABCD-2345" required autofocus style="font-size:1.3rem;letter-spacing:.15em;text-align:center;text-transform:uppercase"></div>
        <button class="btn btn-primary" style="width:100%">Register and sign in</button>
    </form>
    <p class="small muted" style="margin:16px 0 0;text-align:center">No code? Ask HR to register your computer. <a href="{{ route('login') }}">Back to sign in</a></p>
</div>
@endsection
