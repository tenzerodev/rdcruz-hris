@extends('layouts.app')
@section('title', 'Offline')
@section('bare', true)
@section('main-class', 'auth')
@section('content')
<div class="card" style="text-align:center">
    <h1>You're offline</h1>
    <p class="muted">The HRIS needs an internet connection. Check your Wi-Fi or mobile data, then try again.</p>
    <button class="btn btn-primary" onclick="location.reload()">Try again</button>
</div>
@endsection
