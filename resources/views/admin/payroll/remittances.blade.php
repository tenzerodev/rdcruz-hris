@extends('layouts.app')
@section('title', 'Remittances')
@section('content')
@php $money = fn ($v) => number_format((float) $v, 2); @endphp
<div class="page-head">
    <div><h1>Remittances — {{ $month->format('F Y') }}</h1><div class="muted">What to remit to GSIS, PhilHealth, Pag-IBIG, BIR and other lenders, from this month's <b>released</b> payrolls (both halves).</div></div>
    <form method="get" class="actions"><input type="month" name="period" value="{{ $period }}" aria-label="Month"><button class="btn">Show</button><button type="button" class="btn" onclick="window.print()">Print</button></form>
</div>
@if ($drafts)
    <div class="alert alert-warn"><x-icon name="alert" />{{ $drafts }} payroll(s) for this month are still drafts and are not included. Release them first.</div>
@endif

@if (! $sections)
    <section class="card"><p class="empty">No released payroll for {{ $month->format('F Y') }}.</p></section>
@else
<div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(170px,1fr));margin-bottom:16px">
    @foreach ($sections as $key => $s)
        <a class="card stat card-link" href="#{{ $key }}"><div class="num">₱{{ $money(\App\Services\RemittanceReport::remitTotal($s)) }}</div><div class="label">{{ $s['title'] }} · {{ count($s['rows']) }}</div></a>
    @endforeach
</div>

@foreach ($sections as $key => $s)
<section class="card" id="{{ $key }}">
    <div class="card-head"><h2>{{ $s['title'] }}</h2><a class="btn btn-sm" href="{{ route('admin.remittances.export', ['section' => $key, 'period' => $period]) }}">Download CSV</a></div>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Employee</th>@if ($s['id_label'])<th>{{ $s['id_label'] }}</th>@endif @foreach ($s['columns'] as $c)<th class="num">{{ $c }}</th>@endforeach</tr></thead>
        <tbody>
        @foreach ($s['rows'] as $r)
            <tr><td>{{ $r['name'] }}<div class="small muted">{{ $r['employee_no'] }}</div></td>
                @if ($s['id_label'])<td class="small">{!! $r['id'] ? e($r['id']) : '<span class="badge badge-warn">missing</span>' !!}</td>@endif
                @foreach ($r['values'] as $v)<td class="num">{{ $v ? $money($v) : '—' }}</td>@endforeach</tr>
        @endforeach
        </tbody>
        <tfoot><tr><th>Total</th>@if ($s['id_label'])<th></th>@endif @foreach ($s['totals'] as $t)<th class="num">{{ $money($t) }}</th>@endforeach</tr></tfoot>
    </table></div>
</section>
@endforeach
<p class="hint">The CSV files list each employee's shares and loans. Each agency has its own upload format (GSIS eRemittance, PhilHealth EPRS, Pag-IBIG eSRS, BIR alphalist), so copy the columns into their template, and check totals against accounting before remitting.</p>
@endif
@endsection
