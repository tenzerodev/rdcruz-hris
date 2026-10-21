@extends('layouts.app')
@section('title', 'Enroll face')
@section('content')
<div class="page-head">
    <div><h1>Enroll face</h1><div class="muted">{{ $employee->full_name }} · {{ $employee->employee_no }}</div></div>
    <a class="btn" href="{{ route('admin.employees.index') }}">Back to employees</a>
</div>
<div id="enroll-app" class="grid grid-2" data-url="{{ route('admin.employees.samples.store', $employee) }}">
    <div class="stack">
        <div class="cam"><video autoplay playsinline muted></video><canvas aria-hidden="true"></canvas><div class="guide" aria-hidden="true"></div></div>
        <div class="status"><div data-status>Starting…</div></div>
    </div>
    <section class="card stack">
        <div>
            <h2>Captures</h2>
            <p class="small muted">The camera captures 5 angles automatically. Use good, even light from the front. Only the face measurements (numbers) are saved, not these photos.</p>
            <div class="thumbs" data-thumbs></div>
        </div>
        <p class="small">Currently enrolled: <b data-count>{{ $employee->face_samples_count }}</b> samples
            @if ($employee->consent_at)· consent recorded {{ $employee->consent_at->format('M j, Y') }}@endif</p>
        <label class="check"><input type="checkbox" name="replace" value="1" {{ $employee->face_samples_count ? 'checked' : '' }}> Replace existing samples</label>
        <label class="check"><input type="checkbox" name="consent" value="1">
            <span>The employee has read the <a href="{{ route('privacy') }}" target="_blank">privacy notice</a> and consents to the collection and processing of their facial data for attendance, under the Data Privacy Act of 2012 (RA 10173).</span></label>
        <div class="actions" style="justify-content:flex-start">
            <button type="button" class="btn btn-primary" data-save disabled>Save face</button>
            <button type="button" class="btn" data-restart>Retake</button>
        </div>
        <div data-msg role="status"></div>
        @if ($employee->face_samples_count)
        <form method="post" action="{{ route('admin.employees.samples.destroy', $employee) }}" onsubmit="return confirm('Delete all face data for this employee? They will not be able to punch until re-enrolled.')">
            @csrf @method('delete')
            <button class="btn btn-danger btn-sm">Delete face data</button>
        </form>
        @endif
    </section>
</div>
@endsection
@push('scripts')
<script src="{{ asset('vendor/face-api/face-api.js') }}"></script>
<script src="{{ asset('js/face-engine.js') }}?v=2"></script>
<script src="{{ asset('js/enroll.js') }}?v=1"></script>
@endpush
