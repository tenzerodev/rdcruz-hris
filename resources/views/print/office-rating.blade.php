@extends('print._layout')
@section('title', $rating->level_name.' — '.$rating->office)
@section('page-size', 'A4 landscape')
@section('content')
@php $n = fn ($v) => $v === null ? '' : number_format($v, 2); $office = $rating->level === 'opcr'; @endphp
<style>.page { width: 297mm; min-height: 210mm; }</style>
<x-print-header />
<h1>{{ $office ? 'OFFICE' : 'DIVISION' }} PERFORMANCE COMMITMENT AND REVIEW ({{ strtoupper($rating->level) }})</h1>
<p style="text-align:justify">I, <b>{{ mb_strtoupper($rating->head?->display_name ?? '____________________') }}</b>, {{ $rating->head?->position }} of the <b>{{ $rating->office }}</b>, commit to deliver and agree to be rated on the attainment of the following targets in accordance with the indicated measures for the period <b>{{ $rating->period->starts_on->format('F j') }} to {{ $rating->period->ends_on->format('F j, Y') }}</b>.</p>
<table class="f" style="margin:3mm 0">
    <tr><td style="width:33%"><div class="sign" style="margin-top:6mm"><div class="who">{{ $rating->head?->display_name }}</div><span class="n">{{ $office ? 'Head of Office' : 'Division Chief' }}</span></div></td>
        <td style="width:33%"><div class="sign" style="margin-top:6mm"><div class="who">{{ $office ? ($settings['agency_head_name'] ?? '') : '' }}</div><span class="n">Approved by · {{ $rating->targets_approved_at?->format('m/d/Y') }}</span></div></td>
        <td><div class="small">Rating scale</div><div class="small">5 – Outstanding · 4 – Very Satisfactory · 3 – Satisfactory · 2 – Unsatisfactory · 1 – Poor</div></td></tr>
</table>
<table class="f small">
    <thead><tr><th rowspan="2" style="width:15%">MFO / PAP</th><th rowspan="2" style="width:19%">SUCCESS INDICATORS</th><th rowspan="2">ALLOTTED BUDGET</th><th rowspan="2">DIVISIONS / INDIVIDUALS ACCOUNTABLE</th><th rowspan="2">ACTUAL ACCOMPLISHMENTS</th><th colspan="4">RATING</th><th rowspan="2">REMARKS</th></tr>
        <tr><th>Q¹</th><th>E²</th><th>T³</th><th>A⁴</th></tr></thead>
    <tbody>
    @foreach (config('hris.ipcr.categories') as $cat => $label)
        @php $rows = $rating->items->where('category', $cat); @endphp
        @continue($rows->isEmpty())
        <tr><td colspan="10" class="sec" style="text-align:left">{{ strtoupper($label) }} ({{ config("hris.ipcr.weights.$cat") * 100 }}%)</td></tr>
        @foreach ($rows as $item)
            <tr><td>{{ $item->output }}</td><td>{{ $item->success_indicator }}</td><td class="r">{{ $item->budget ? number_format($item->budget, 2) : '' }}</td><td>{{ $item->accountable }}</td><td>{{ $item->accomplishment }}</td>
                <td class="c">{{ $item->q }}</td><td class="c">{{ $item->e }}</td><td class="c">{{ $item->t }}</td><td class="c">{{ $n($item->average()) }}</td><td>{{ $item->remarks }}</td></tr>
        @endforeach
    @endforeach
    </tbody>
</table>
<table class="f" style="margin-top:2mm">
    <tr>@foreach ($computed['categories'] as $cat => $avg)<th>{{ config("hris.ipcr.categories.$cat") }}</th>@endforeach<th>FINAL AVERAGE RATING</th><th>ADJECTIVAL RATING</th></tr>
    <tr>@foreach ($computed['categories'] as $avg)<td class="c">{{ $n($avg) }}</td>@endforeach<td class="c"><b>{{ $n($rating->final_rating ?? $computed['final']) }}</b></td><td class="c"><b>{{ $rating->adjectival ?? $computed['adjectival'] }}</b></td></tr>
</table>
<table class="f" style="margin-top:2mm"><tr>
    <td style="width:50%"><div class="lbl">Assessment / comments (Performance Management Team)</div>{{ $rating->comments }}</td>
    <td><div class="sign" style="margin-top:4mm"><div class="who">&nbsp;</div><span class="n">Assessed by (PMT) · {{ $rating->rated_at?->format('m/d/Y') }}</span></div>
        <div class="sign" style="margin-top:6mm"><div class="who">{{ $settings['agency_head_name'] ?? '' }}</div><span class="n">Final rating by (Head of Agency) · {{ $rating->finalized_at?->format('m/d/Y') }}</span></div></td>
</tr></table>
<p class="small">Legend: 1 – Quality, 2 – Efficiency, 3 – Timeliness, 4 – Average</p>
@endsection
