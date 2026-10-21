@extends('print._layout')
@section('title', 'WFH accomplishment report — '.$employee->full_name.' — '.$month->format('F Y'))
@section('content')
@php
    $times = fn ($date) => collect($punches[$date] ?? [])->map(fn ($p) => $p->punched_at->format('g:i A'))->join(' / ');
@endphp
<x-print-header />
<h1>WORK-FROM-HOME ACCOMPLISHMENT REPORT</h1>
<p class="c small">For the month of {{ $month->format('F Y') }}</p>
<table class="f" style="margin-top:5mm">
    <tr><td style="width:30%"><div class="lbl">Name</div></td><td class="val">{{ $employee->display_name }}</td></tr>
    <tr><td><div class="lbl">Position / office</div></td><td class="val">{{ $employee->position }}{{ $employee->office ? ', '.$employee->office : '' }}</td></tr>
</table>
<table class="f" style="margin-top:5mm">
    <thead><tr><th style="width:18%">Date</th><th style="width:22%">Time in / out (WFH)</th><th>Accomplishments / outputs</th></tr></thead>
    <tbody>
    @forelse ($reports as $r)
        <tr><td>{{ $r->work_date->format('D, M j') }}</td><td class="small">{{ $times($r->work_date->toDateString()) ?: '—' }}</td><td style="white-space:pre-line">{{ $r->accomplishments }}</td></tr>
    @empty
        <tr><td colspan="3" class="c">No accomplishments written for this month.</td></tr>
    @endforelse
    </tbody>
</table>
<p style="margin-top:6mm">I certify that the above accomplishments were rendered while on a work-from-home arrangement.</p>
<div style="display:flex;gap:12mm;margin-top:16mm">
    <div class="sign" style="flex:1"><div class="who">{{ $employee->display_name }}</div><span class="n">Employee</span></div>
    <div class="sign" style="flex:1"><div class="who">{{ $employee->verifier_name }}</div><span class="n">Reviewed by (Immediate supervisor)</span></div>
</div>
<x-verify-stamp type="wfh_report" :key="'wfh:'.$employee->id.':'.$month->format('Y-m')" :employee="$employee" :title="'WFH accomplishment report '.$month->format('F Y').' — '.$employee->full_name" :summary="[
    'Employee' => $employee->full_name, 'Month' => $month->format('F Y'), 'Days reported' => $reports->count(),
    'Days with WFH time records' => count($punches),
]" style="margin-top:10mm" />
@endsection
