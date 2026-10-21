@extends('layouts.app')
@section('title', 'My data & privacy')
@section('content')
<div class="page-head">
    <div><h1>My data &amp; privacy</h1><div class="muted">What the HRIS keeps about you, and your rights under the Data Privacy Act. <a href="{{ route('privacy') }}" target="_blank">Read the privacy notice</a>.</div></div>
</div>
<div class="grid grid-2">
    <section class="card">
        <h2>What we hold</h2>
        <dl class="kv">
            <dt>Profile &amp; PDS</dt><dd>Your 201 file{{ $e->pds_updated_at ? ', PDS updated '.$e->pds_updated_at->format('M j, Y') : '' }}</dd>
            <dt>Documents</dt><dd>{{ $counts['documents'] }} uploaded</dd>
            <dt>Time records</dt><dd>{{ number_format($counts['punches']) }} punches{{ $counts['photos'] ? ', '.$counts['photos'].' with a photo' : '' }}</dd>
            <dt>Leave applications</dt><dd>{{ $counts['leave'] }}</dd>
            <dt>Face recognition</dt><dd>@if ($e->consent_at && $counts['face'])On — consent given {{ $e->consent_at->format('M j, Y') }}; {{ $counts['face'] }} face template(s), no photos @else Off @endif</dd>
        </dl>
        <a class="btn btn-primary" href="{{ route('me.privacy.export') }}" style="margin-top:16px">Download my data</a>
        <p class="hint">A JSON file with everything above. Found something wrong? Ask HR to correct it.</p>
    </section>
    <section class="card">
        <h2>Face recognition consent</h2>
        @if ($e->consent_at || $counts['face'])
            <p class="small">You can withdraw your consent at any time. Your face templates and punch photos will be deleted right away, and HR will be told so they can arrange another way to record your attendance (for example, a logbook).</p>
            <form method="post" action="{{ route('me.privacy.withdraw') }}" onsubmit="return confirm('Delete your face data and stop using face recognition?')">
                @csrf
                <div class="field"><label for="password">Your password</label><input type="password" id="password" name="password" required autocomplete="current-password"></div>
                <button class="btn btn-danger">Withdraw consent and delete my face data</button>
            </form>
        @else
            <p class="small muted">Face recognition is off for you. To use it, see HR to enroll; you'll be asked for consent first.</p>
        @endif
    </section>
</div>
@endsection
