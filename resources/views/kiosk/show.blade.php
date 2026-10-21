@extends('layouts.app')
@section('title', 'Kiosk')
@section('bare', true)
@section('main-class', '')
@section('content')
<div id="punch-app" class="kiosk" data-mode="kiosk" data-url="{{ route('kiosk.punch') }}" data-token-url="{{ route('kiosk.token') }}">
    <header>
        <div>
            <div class="clock" data-clock>--:--</div>
            <div class="muted" data-date></div>
        </div>
        <div style="text-align:right">
            <strong>{{ \App\Models\Setting::get('agency_name') }}</strong>
            <div class="muted small">{{ $kiosk->name }}{{ $kiosk->location ? ' · '.$kiosk->location : '' }}</div>
        </div>
    </header>
    <div class="stack">
        @include('kiosk._camera')
    </div>
    <p class="muted small" style="text-align:center;margin:0">Your photo is taken with each punch for attendance verification, as stated in the agency <a href="{{ route('privacy') }}">privacy notice</a>.</p>
</div>
@endsection
@push('scripts')
<script src="{{ asset('vendor/face-api/face-api.js') }}"></script>
<script src="{{ asset('js/face-engine.js') }}?v=2"></script>
<script src="{{ asset('js/punch.js') }}?v=2"></script>
@endpush
