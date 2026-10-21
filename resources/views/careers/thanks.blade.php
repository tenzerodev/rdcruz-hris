@extends('layouts.app')
@section('title', 'Application received')
@section('bare', true)
@section('content')
@include('careers._head')
<section class="card" style="max-width:560px;margin:24px auto;text-align:center">
    <h1>Thank you!</h1>
    <p>Your application was received. Your reference number is</p>
    <p style="font-size:1.6em;font-weight:700;letter-spacing:1px">{{ $reference }}</p>
    <p class="muted">Keep it to <a href="{{ route('careers.status') }}">check your application status</a>. We sent a copy to your email.</p>
</section>
@endsection
