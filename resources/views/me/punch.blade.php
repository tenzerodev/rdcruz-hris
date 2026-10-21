@extends('layouts.app')
@section('title', 'Time In / Out')
@section('content')
<div class="narrow" style="margin:0 auto">
    @if ($wfh && $wfh->require_device && ! $device)
        <div class="alert alert-warn"><x-icon name="laptop" /><div><b>Work from home today.</b> Time in from your agency-issued computer. If this is that computer, <a href="{{ route('me.devices') }}">register it</a> with the code from HR.</div></div>
    @elseif (! $employee->allow_personal && ! $wfh)
        <div class="alert alert-warn">Your account is set to punch at the office kiosk only.</div>
    @elseif (! $enrolled)
        <div class="alert alert-warn">Your face isn't enrolled yet. Please see the HR or admin officer.</div>
    @elseif (! $next)
        <div class="alert alert-ok">You have completed all punches for today.</div>
        <a class="btn" href="{{ route('me.index') }}">Back</a>
    @else
        <div id="punch-app" class="stack" data-mode="personal" data-url="{{ url('/me/punch') }}" data-geo="{{ $geofence && ! $wfh ? '1' : '0' }}">
            <div class="page-head" style="margin:0">
                <div><h1>Time In / Out</h1><div class="muted"><span data-clock></span> · <span data-date></span></div></div>
            </div>
            @include('kiosk._camera')
            @if ($wfh)
                <p class="small muted" style="text-align:center"><span class="badge badge-info">Work from home</span>{{ $device ? ' · '.$device->name : '' }}{{ $wfh->reference_no ? ' · '.$wfh->reference_no : '' }}</p>
            @elseif ($geofence)<p class="small muted" style="text-align:center">Your location is checked against the office location.</p>@endif
            <div data-after hidden style="text-align:center"><a class="btn btn-primary" href="{{ route('me.index') }}">Back to My DTR</a>@if ($wfh) <a class="btn" href="{{ route('me.wfh') }}">Write today's accomplishments</a>@endif</div>
        </div>
    @endif
</div>
@endsection
@push('scripts')
<script src="{{ asset('vendor/face-api/face-api.js') }}"></script>
<script src="{{ asset('js/face-engine.js') }}?v=2"></script>
<script src="{{ asset('js/punch.js') }}?v=2"></script>
@endpush
