@extends('layouts.app')
@section('title', 'Annual tax '.$year)
@section('content')
@php $money = fn ($v) => number_format((float) $v, 2); @endphp
<div class="page-head">
    <div><h1>Annual tax &amp; BIR 2316 — {{ $year }}</h1>
        <div class="muted">From released payslips. Tax due on the year's taxable pay compared with tax withheld: collect the shortfall or refund the excess in the last payroll of the year.</div></div>
    <form class="inline-form" method="get">
        <div class="field"><label for="year" class="sr">Year</label><input type="number" id="year" name="year" value="{{ $year }}" min="2000" max="2100" style="width:100px"></div>
        <button class="btn">Show</button>
    </form>
</div>
<div class="actions" style="justify-content:flex-start;margin-bottom:16px">
    <a class="btn" href="{{ route('admin.tax.certificate', ['year' => $year]) }}" target="_blank">Print all 2316 data sheets</a>
    <a class="btn" href="{{ request()->fullUrlWithQuery(['format' => 'csv']) }}">Download alphalist (CSV)</a>
</div>
<section class="card">
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Employee</th><th class="num">Gross</th><th class="num">Benefits (exempt / taxable)</th><th class="num">Contributions</th><th class="num">Taxable</th><th class="num">Tax due</th><th class="num">Withheld</th><th class="num">Adjustment</th><th></th></tr></thead>
        <tbody>
        @forelse ($rows as $r)
            <tr>
                <td><b>{{ $r['employee']->full_name }}</b><div class="small muted">{{ $r['employee']->employee_no }} · TIN {{ $r['employee']->tin ?: 'not on file' }}</div></td>
                <td class="num">{{ $money($r['gross']) }}</td>
                <td class="num">{{ $money($r['benefits_exempt']) }}<div class="small muted">{{ $r['benefits_taxable'] > 0 ? $money($r['benefits_taxable']).' taxable' : '' }}</div></td>
                <td class="num">{{ $money($r['contributions']) }}</td>
                <td class="num">{{ $money($r['taxable']) }}</td>
                <td class="num">{{ $money($r['tax_due']) }}</td>
                <td class="num">{{ $money($r['withheld']) }}</td>
                <td class="num">@if (abs($r['difference']) < 0.01)<span class="badge badge-ok">Even</span>@elseif ($r['difference'] > 0)<span class="badge badge-warn">Collect {{ $money($r['difference']) }}</span>@else<span class="badge badge-info">Refund {{ $money(-$r['difference']) }}</span>@endif</td>
                <td><a class="btn btn-sm" href="{{ route('admin.tax.certificate', ['employee' => $r['employee']->id, 'year' => $year]) }}" target="_blank">2316</a></td>
            </tr>
        @empty <tr><td colspan="9" class="empty">No released payslips for {{ $year }}.</td></tr> @endforelse
        </tbody>
    </table></div>
    <p class="hint">Add a collection as a deduction (or a refund as non-taxable pay) on each employee's December payslip with Adjust. File the official BIR Form 2316 and alphalist through eBIRForms/eFPS using these figures. Employees with another employer in the same year need the previous employer's 2316 added.</p>
</section>
@endsection
