@extends('print._layout')
@section('title', 'Clearance — '.$c->employee->full_name)
@section('content')
@php $e = $c->employee; @endphp
<x-print-header />
<h1>CLEARANCE CERTIFICATE</h1>
<p class="c small">From money, property and work-related accountabilities</p>
<table class="f" style="margin-top:4mm">
    <tr><td style="width:35%"><div class="lbl">Name</div></td><td class="val">{{ $e->display_name }}</td></tr>
    <tr><td><div class="lbl">Position / office</div></td><td class="val">{{ $e->position }}{{ $e->office ? ', '.$e->office : '' }}</td></tr>
    <tr><td><div class="lbl">Purpose</div></td><td class="val">{{ $c->reason }} effective {{ $c->separation_date->format('F j, Y') }}</td></tr>
</table>
<p style="margin-top:4mm">This is to certify that the above-named is cleared of accountabilities by the following offices:</p>
<table class="f">
    <thead><tr><th style="width:24%">Office</th><th>Checked</th><th style="width:24%">Cleared by</th><th style="width:15%">Date</th></tr></thead>
    <tbody>@foreach ($c->items as $i)<tr><td>{{ $i->office }}</td><td class="small">{{ $i->checks }}{{ $i->remarks ? ' — '.$i->remarks : '' }}</td><td>{{ $i->cleared_by }}</td><td>{{ $i->cleared_at?->format('M j, Y') }}</td></tr>@endforeach</tbody>
</table>
@if ($c->status === 'completed')
    <div style="margin-top:16mm;margin-left:55%" class="sign"><div class="who">{{ $settings['hr_head_name'] ?? '' }}</div><span class="n">{{ $settings['hr_head_title'] ?? 'HR head' }}</span></div>
    <x-verify-stamp type="clearance" :key="'clearance:'.$c->id" :employee="$e" :title="'Clearance — '.$e->full_name" :summary="[
        'Employee' => $e->full_name, 'Separation' => $c->separation_date->format('M j, Y').' ('.$c->reason.')', 'Completed' => $c->completed_at->format('M j, Y'),
    ]" style="margin-top:8mm" />
@else
    <p class="c" style="margin-top:10mm"><b>IN PROGRESS — NOT YET COMPLETE</b></p>
@endif
@endsection
