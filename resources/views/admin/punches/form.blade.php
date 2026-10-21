@extends('layouts.app')
@section('title', $punch->exists ? 'Edit punch' : 'Add manual punch')
@section('content')
<div class="card narrow">
    <h1>{{ $punch->exists ? 'Edit punch' : 'Add manual punch' }}</h1>
    <p class="muted small">Manual entries and edits are saved in the audit log with your name.</p>
    <form method="post" action="{{ $punch->exists ? route('admin.punches.update', $punch) : route('admin.punches.store') }}">
        @csrf @if ($punch->exists) @method('put') @endif
        @if ($punch->exists)
            <p><b>{{ $punch->employee->full_name }}</b><br>{{ $punch->label }} · {{ $punch->work_date->format('l, F j, Y') }}</p>
        @else
            <div class="field"><label for="employee_id">Employee</label>
                <select id="employee_id" name="employee_id" required><option value="">Choose…</option>
                    @foreach ($employees as $e)<option value="{{ $e->id }}" @selected(old('employee_id', $punch->employee_id) == $e->id)>{{ $e->full_name }} ({{ $e->employee_no }})</option>@endforeach
                </select></div>
            <div class="row">
                <div class="field"><label for="work_date">Date</label><input type="date" id="work_date" name="work_date" value="{{ old('work_date', $punch->work_date?->toDateString()) }}" required></div>
                <div class="field"><label for="type">Type</label>
                    <select id="type" name="type">@foreach (\App\Models\Punch::LABELS as $t => $l)<option value="{{ $t }}" @selected(old('type', $punch->type) === $t)>{{ $l }}</option>@endforeach</select></div>
            </div>
        @endif
        <div class="field"><label for="time">Time</label><input type="time" id="time" name="time" value="{{ old('time', $punch->punched_at?->format('H:i')) }}" required></div>
        <div class="field"><label for="remarks">Reason / remarks</label><input type="text" id="remarks" name="remarks" value="{{ old('remarks', $punch->exists ? '' : '') }}" placeholder="e.g. Official business, approved by supervisor" required></div>
        <div class="actions" style="justify-content:flex-start">
            <button class="btn btn-primary">Save</button>
            <a class="btn" href="{{ url()->previous() }}">Cancel</a>
        </div>
    </form>
</div>
@endsection
