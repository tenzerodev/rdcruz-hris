@extends('print._layout')
@section('title', $m->kind_name.' — '.$m->employee->full_name)
@section('content')
@php $money = fn ($v) => number_format((float) $v, 2); $e = $m->employee; @endphp
<x-print-header />
<h1>{{ mb_strtoupper($m->kind_name) }}</h1>
<p class="c small">Computation · {{ $m->date->format('F j, Y') }}</p>
<table class="f" style="margin-top:5mm">
    <tr><td style="width:40%"><div class="lbl">Name</div></td><td class="val">{{ $e->display_name }}</td></tr>
    <tr><td><div class="lbl">Position / office</div></td><td class="val">{{ $e->position }}{{ $e->office ? ', '.$e->office : '' }}</td></tr>
    @if ($m->reason)<tr><td><div class="lbl">Reason</div></td><td class="val">{{ $m->reason }}</td></tr>@endif
</table>
<table class="f" style="margin-top:5mm">
    <tr><td>Vacation leave credits</td><td style="text-align:right">{{ rtrim(rtrim(number_format($m->vl_days, 3), '0'), '.') ?: '0' }} days</td></tr>
    <tr><td>Sick leave credits</td><td style="text-align:right">{{ rtrim(rtrim(number_format($m->sl_days, 3), '0'), '.') ?: '0' }} days</td></tr>
    <tr><td>{{ $m->kind === 'terminal' ? 'Highest monthly salary received' : 'Monthly salary' }}</td><td style="text-align:right">₱ {{ $money($m->salary) }}</td></tr>
    <tr><td>Constant factor</td><td style="text-align:right">{{ $m->factor }}</td></tr>
    <tr><td><b>Amount</b> ({{ $money($m->salary) }} × {{ $m->vl_days + $m->sl_days }} × {{ $m->factor }})</td><td style="text-align:right"><b>₱ {{ $money($m->amount) }}</b></td></tr>
</table>
<p class="small" style="margin-top:4mm">The days above were deducted from the leave card on {{ $m->date->format('F j, Y') }}. Subject to the availability of funds and the agency's rules on monetization.</p>
<div style="display:flex;gap:12mm;margin-top:16mm">
    <div class="sign" style="flex:1"><div class="who">{{ $m->creator?->name }}</div><span class="n">Prepared by</span></div>
    <div class="sign" style="flex:1"><div class="who">{{ $settings['hr_head_name'] ?? '' }}</div><span class="n">{{ $settings['hr_head_title'] ?? 'HR head' }}</span></div>
</div>
<x-verify-stamp type="monetization" :key="'monetization:'.$m->id" :employee="$e" :title="$m->kind_name.' — '.$e->full_name" :summary="[
    'Employee' => $e->full_name, 'Date' => $m->date->format('M j, Y'), 'Days' => $m->vl_days + $m->sl_days, 'Amount' => '₱'.$money($m->amount),
]" style="margin-top:8mm" />
@endsection
