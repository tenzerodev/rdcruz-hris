@extends('print._layout')
@section('title', 'Service Record — '.$employee->full_name)
@section('page-size', 'A4 landscape')
@section('content')
<x-print-header />
<h1>SERVICE RECORD</h1>
<table class="f" style="margin-bottom:4mm">
    <tr><td><div class="lbl">Name</div><div class="val">{{ mb_strtoupper($employee->last_name) }}, {{ $employee->first_name }} {{ $employee->middle_name }} {{ $employee->suffix }}</div></td>
        <td><div class="lbl">Birth date</div><div class="val">{{ $employee->birth_date?->format('F j, Y') }}</div></td>
        <td><div class="lbl">Employee no.</div><div class="val">{{ $employee->employee_no }}</div></td></tr>
</table>
<p class="small">This is to certify that the employee named above actually rendered services in this Office as shown by the service record below, each line of which is supported by appointment and other papers actually issued by this Office and approved by the authorities concerned.</p>
<table class="f">
    <thead><tr><th colspan="2" class="small">SERVICE (Inclusive dates)</th><th colspan="3" class="small">RECORD OF APPOINTMENT</th><th colspan="2" class="small">OFFICE ENTITY / DIVISION</th><th class="small" rowspan="2">L/V ABS W/O PAY</th><th class="small" rowspan="2">SEPARATION (Date / Cause)</th></tr>
        <tr><th class="small">From</th><th class="small">To</th><th class="small">Designation</th><th class="small">Status</th><th class="small">Salary (annual)</th><th class="small">Station / Place</th><th class="small">Branch</th></tr></thead>
    <tbody>
    @forelse ($employee->serviceRecords as $r)
        <tr><td class="c">{{ $r->date_from->format('m/d/Y') }}</td><td class="c">{{ $r->date_to?->format('m/d/Y') ?? 'present' }}</td><td>{{ $r->designation }}</td><td>{{ $r->status }}</td>
            <td class="r">{{ $r->annual_salary ? number_format($r->annual_salary, 2) : '' }}</td><td>{{ $r->office }}</td><td>{{ $r->branch }}</td><td>{{ $r->leave_without_pay }}</td><td>{{ $r->separation }}</td></tr>
    @empty <tr><td colspan="9" class="c">No entries.</td></tr> @endforelse
    </tbody>
</table>
<p class="small">Issued in compliance with Executive Order No. 54 dated August 10, 1954, and in accordance with Circular No. 58 dated August 10, 1954 of the System.</p>
<div style="display:flex;justify-content:space-between;margin-top:10mm"><div class="small">Date: {{ now()->format('F j, Y') }}</div>
    <div class="sign" style="margin:0"><div class="who">&nbsp;</div><span class="n">Certified correct — HR Officer</span></div></div>
@php $sr = $employee->serviceRecords; @endphp
<x-verify-stamp type="service_record" :key="'service_record:'.$employee->id" :employee="$employee" :title="'Service Record — '.$employee->full_name" :summary="[
    'Name' => $employee->full_name, 'Entries' => $sr->count(),
    'Service from' => $sr->first()?->date_from->format('M j, Y'), 'Latest designation' => $sr->last()?->designation,
    'Latest status' => $sr->last()?->status, 'Date issued' => now()->format('M j, Y'),
]" />
@endsection
