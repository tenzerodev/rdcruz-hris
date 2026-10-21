@extends('layouts.app')
@section('title', 'Kiosk not activated')
@section('bare', true)
@section('main-class', 'auth')
@section('content')
<div class="card">
    <h1>This device is not a kiosk yet</h1>
    <p class="muted">An administrator needs to activate it. On this device, log in as admin, open <b>Kiosks</b>, and tap <b>Activate this device</b>.</p>
    <a class="btn btn-primary" href="{{ route('login') }}">Admin log in</a>
</div>
@endsection
