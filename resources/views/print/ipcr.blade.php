@extends('print._layout')
@section('title', 'IPCR — '.$ipcr->employee->full_name)
@section('page-size', 'A4 landscape')
@section('content')
@php $n = fn ($v) => $v === null ? '' : number_format($v, 2); @endphp
<x-print-header />
<h1>INDIVIDUAL PERFORMANCE COMMITMENT AND REVIEW (IPCR)</h1>
<p style="text-align:justify">I, <b>{{ mb_strtoupper($ipcr->employee->display_name) }}</b>, {{ $ipcr->employee->position }} of the {{ $ipcr->employee->office }}, commit to deliver and agree to be rated on the attainment of the following targets in accordance with the indicated measures for the period <b>{{ $ipcr->period->starts_on->format('F j') }} to {{ $ipcr->period->ends_on->format('F j, Y') }}</b>.</p>
<table class="f" style="margin:3mm 0">
    <tr><td style="width:33%"><div class="sign" style="margin-top:6mm"><div class="who">{{ $ipcr->employee->display_name }}</div><span class="n">Ratee · Date: {{ $ipcr->created_at->format('m/d/Y') }}</span></div></td>
        <td style="width:33%"><div class="sign" style="margin-top:6mm"><div class="who">{{ $ipcr->rater?->display_name }}</div><span class="n">Approved by (Immediate Supervisor) · {{ $ipcr->targets_approved_at?->format('m/d/Y') }}</span></div></td>
        <td><div class="small">Rating scale</div><div class="small">5 – Outstanding · 4 – Very Satisfactory · 3 – Satisfactory · 2 – Unsatisfactory · 1 – Poor</div></td></tr>
</table>
<table class="f">
    <thead><tr><th rowspan="2" style="width:17%">OUTPUT (MFO / PAP)</th><th rowspan="2" style="width:22%">SUCCESS INDICATORS (Targets + Measures)</th><th rowspan="2">ACTUAL ACCOMPLISHMENTS</th><th colspan="4">RATING</th><th rowspan="2" style="width:12%">REMARKS</th></tr>
        <tr><th>Q¹</th><th>E²</th><th>T³</th><th>A⁴</th></tr></thead>
    <tbody>
    @foreach (config('hris.ipcr.categories') as $cat => $label)
        @php $rows = $ipcr->items->where('category', $cat); @endphp
        @continue($rows->isEmpty())
        <tr><td colspan="8" class="sec" style="text-align:left">{{ strtoupper($label) }} ({{ config("hris.ipcr.weights.$cat") * 100 }}%)</td></tr>
        @foreach ($rows as $item)
            <tr><td>{{ $item->output }}</td><td>{{ $item->success_indicator }}</td><td>{{ $item->accomplishment }}</td>
                <td class="c">{{ $item->q }}</td><td class="c">{{ $item->e }}</td><td class="c">{{ $item->t }}</td><td class="c">{{ $n($item->average()) }}</td><td>{{ $item->remarks }}</td></tr>
        @endforeach
    @endforeach
    </tbody>
</table>
<table class="f" style="margin-top:2mm">
    <tr><th>Category</th>@foreach ($rating['categories'] as $cat => $avg)<th>{{ config("hris.ipcr.categories.$cat") }}</th>@endforeach<th>FINAL AVERAGE RATING</th><th>ADJECTIVAL RATING</th></tr>
    <tr><td class="small">Average</td>@foreach ($rating['categories'] as $avg)<td class="c">{{ $n($avg) }}</td>@endforeach<td class="c"><b>{{ $n($rating['final']) }}</b></td><td class="c"><b>{{ $rating['adjectival'] }}</b></td></tr>
</table>
<table class="f" style="margin-top:2mm">
    <tr><td style="width:50%"><div class="lbl">Comments and recommendations for development purposes</div>{{ $ipcr->comments }}<div style="margin-top:2mm" class="lbl">Development plan</div>{{ $ipcr->development_plan }}</td>
        <td><div class="sign" style="margin-top:4mm"><div class="who">{{ $ipcr->employee->display_name }}</div><span class="n">Discussed with (Ratee)</span></div>
            <div class="sign" style="margin-top:6mm"><div class="who">{{ $ipcr->rater?->display_name }}</div><span class="n">Assessed by (Supervisor) · {{ $ipcr->rated_at?->format('m/d/Y') }}</span></div>
            <div class="sign" style="margin-top:6mm"><div class="who">&nbsp;</div><span class="n">Final rating by (Head of Office) · {{ $ipcr->finalized_at?->format('m/d/Y') }}</span></div></td></tr>
</table>
<p class="small">Legend: 1 – Quality, 2 – Efficiency, 3 – Timeliness, 4 – Average</p>
<x-verify-stamp type="ipcr" :key="'ipcr:'.$ipcr->id" :employee="$ipcr->employee" :title="'IPCR '.$ipcr->period->name.' — '.$ipcr->employee->full_name" :summary="[
    'Employee' => $ipcr->employee->full_name, 'Rating period' => $ipcr->period->name, 'Status' => $ipcr->status_label,
    'Final rating' => $ipcr->final_rating ? number_format($ipcr->final_rating, 2) : null, 'Adjectival rating' => $ipcr->adjectival,
]" />
@endsection
