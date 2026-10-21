@extends('layouts.app')
@section('title', 'CSC Form 48')
@section('content')
<div class="page-head"><div><h1>CSC Form No. 48 — Daily Time Record</h1><div class="muted">Prints two copies per employee on one landscape page (A4 or Legal).</div></div></div>
<form class="card" method="get" action="{{ route('admin.dtr.print') }}" target="_blank">
    <div class="row">
        <div class="field"><label for="month">Month</label><input type="month" id="month" name="month" value="{{ $month }}" required></div>
        <div class="field"><label for="office">Office</label>
            <select id="office" name="office"><option value="">All offices</option>@foreach ($offices as $o)<option>{{ $o }}</option>@endforeach</select></div>
    </div>
    <div class="field">
        <label for="employee_ids">Employees</label>
        <select id="employee_ids" name="employee_ids[]" multiple size="12">
            @foreach ($employees as $e)<option value="{{ $e->id }}">{{ $e->full_name }} — {{ $e->employee_no }}</option>@endforeach
        </select>
        <div class="hint">Leave none selected to print everyone (in the chosen office). Hold Ctrl/⌘ to pick several.</div>
    </div>
    <button class="btn btn-primary">Open printable DTR</button>
</form>
@endsection
