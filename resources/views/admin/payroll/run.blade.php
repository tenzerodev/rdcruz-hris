@extends('layouts.app')
@section('title', 'Payroll '.$run->label)
@section('content')
@php $money = fn ($v) => number_format((float) $v, 2); $minNet = (float) config('hris.payroll.min_net_pay', 0); @endphp
<div class="page-head">
    <div><h1>Payroll — {{ $run->label }}</h1>
        <div class="muted">
            @if ($run->isReleased())<span class="badge badge-ok">Released</span> {{ $run->released_at->format('M j, Y g:i A') }} by {{ $run->releaser?->name }} · {{ $totals['viewed'] }} of {{ $slips->count() }} employees have opened theirs
            @else<span class="badge badge-warn">Draft</span> Employees can't see these payslips yet.@endif
            {{ $run->pay_date ? ' · Pay date '.$run->pay_date->format('M j, Y') : '' }}
        </div></div>
    <div class="actions">
        <a class="btn" href="{{ route('admin.payroll.runs.print', $run) }}" target="_blank">Print all payslips</a>
        <a class="btn" href="{{ route('admin.payroll.runs.export', $run) }}">Register (CSV)</a>
        <a class="btn" href="{{ route('admin.payroll.runs.bank', $run) }}">Bank file (CSV)</a>
    </div>
</div>

@unless ($readOnly)
<div class="actions" style="justify-content:flex-start;margin-bottom:16px">
    @if ($run->isReleased())
        <form method="post" action="{{ route('admin.payroll.runs.withdraw', $run) }}" onsubmit="return confirm('Take this payroll back to draft? Employees will stop seeing these payslips.')">@csrf<button class="btn">Withdraw to draft</button></form>
    @else
        <form method="post" action="{{ route('admin.payroll.runs.release', $run) }}" onsubmit="return confirm('Release {{ $slips->count() }} payslips? Employees will be able to see them and will get an email.')">@csrf<button class="btn btn-primary">Release to employees</button></form>
        <form method="post" action="{{ route('admin.payroll.runs.regenerate', $run) }}" onsubmit="return confirm('Recompute from the latest attendance? Items you added by hand are kept.')">@csrf<button class="btn">Recompute from attendance</button></form>
        <form method="post" action="{{ route('admin.payroll.runs.destroy', $run) }}" onsubmit="return confirm('Delete this draft payroll?')">@csrf @method('delete')<button class="btn btn-danger">Delete draft</button></form>
    @endif
</div>
@endunless

<div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(170px,1fr));margin-bottom:16px">
    <div class="card stat"><div class="num">{{ $slips->count() }}</div><div class="label">Payslips</div></div>
    <div class="card stat"><div class="num">₱{{ $money($totals['gross']) }}</div><div class="label">Gross</div></div>
    <div class="card stat"><div class="num">₱{{ $money($totals['deductions']) }}</div><div class="label">Deductions (incl. ₱{{ $money($totals['tax']) }} tax)</div></div>
    <div class="card stat"><div class="num">₱{{ $money($totals['net']) }}</div><div class="label">Net pay</div></div>
</div>

<section class="card">
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Employee</th><th class="num">Gross</th><th class="num">GSIS</th><th class="num">PhilHealth</th><th class="num">Pag-IBIG</th><th class="num">Tax</th><th class="num">Loans &amp; other</th><th class="num">Net pay</th><th></th></tr></thead>
        <tbody>
        @forelse ($slips as $s)
            @php
                $d = collect($s->deductions);
                $pick = fn ($label) => $d->where('label', $label)->sum('amount');
                $other = $d->whereIn('kind', ['other', 'loan'])->sum('amount');
                $low = $s->net >= 0 && $s->net < $minNet && ! $run->isBonus();
            @endphp
            <tr @if ($s->net < 0) style="background:var(--error-soft)" @endif>
                <td><b>{{ $s->employee->full_name }}</b><div class="small muted">{{ $s->inputs['type_label'] ?? '' }} · {{ $s->employee->employee_no }}
                    @if (($s->inputs['days_absent'] ?? 0) || ($s->inputs['lwop_days'] ?? 0)) · {{ $s->inputs['days_absent'] }} absent{{ ($s->inputs['lwop_days'] ?? 0) ? ', '.($s->inputs['lwop_days'] + 0).' LWOP' : '' }}@endif</div></td>
                <td class="num">{{ $money($s->gross) }}</td>
                <td class="num">{{ $money($pick('GSIS personal share')) }}</td>
                <td class="num">{{ $money($pick('PhilHealth')) }}</td>
                <td class="num">{{ $money($pick('Pag-IBIG')) }}</td>
                <td class="num">{{ $money($s->tax) }}</td>
                <td class="num">{{ $other ? $money($other) : '—' }}</td>
                <td class="num"><b>{{ $money($s->net) }}</b>@if ($low)<div><span class="badge badge-warn" title="Below the lowest net take-home pay of ₱{{ $money($minNet) }}">Below ₱{{ number_format($minNet) }}</span></div>@endif</td>
                <td><div class="actions">
                    <a class="btn btn-sm" href="{{ route('me.payslips.show', $s) }}" target="_blank">View</a>
                    @if (! $run->isReleased() && ! $readOnly)<a class="btn btn-sm" href="{{ route('admin.payslips.edit', $s) }}">Adjust</a>@endif
                </div></td>
            </tr>
        @empty <tr><td colspan="9" class="empty">No employees paid by the agency.</td></tr> @endforelse
        </tbody>
    </table></div>
    <p class="hint">Loans and recurring deductions come from <a href="{{ route('admin.loans.index') }}">Loans &amp; deductions</a>; change them there and recompute. COS and job order: no GSIS/PhilHealth/Pag-IBIG or tax is withheld here; add the deductions in their contract (e.g. withholding tax option) with Adjust. Service providers and DBP personnel are paid by their own agency and have no payslip.</p>
</section>
@endsection
