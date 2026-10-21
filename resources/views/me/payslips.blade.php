@extends('layouts.app')
@section('title', 'Payslips')
@section('content')
@php $money = fn ($v) => '₱'.number_format((float) $v, 2); @endphp
<div class="page-head">
    <div><h1>Payslips</h1><div class="muted">Your pay for each released payroll. Only you and the payroll office can see these.</div></div>
</div>
<div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(170px,1fr));margin-bottom:16px">
    <div class="card stat"><div class="num">{{ $money($ytd['gross']) }}</div><div class="label">Gross pay, {{ $year }}</div></div>
    <div class="card stat"><div class="num">{{ $money($ytd['tax']) }}</div><div class="label">Tax withheld, {{ $year }}</div></div>
    <div class="card stat"><div class="num">{{ $money($ytd['net']) }}</div><div class="label">Net pay, {{ $year }}</div></div>
</div>
<section class="card">
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Pay period</th><th>Pay date</th><th class="num">Gross</th><th class="num">Deductions</th><th class="num">Net pay</th><th></th></tr></thead>
        <tbody>
        @forelse ($slips as $s)
            <tr>
                <td><b>{{ $s->run->label }}</b> @unless ($s->viewed_at)<span class="badge badge-info">New</span>@endunless</td>
                <td>{{ $s->run->pay_date?->format('M j, Y') ?? '—' }}</td>
                <td class="num">{{ $money($s->gross) }}</td>
                <td class="num">{{ $money($s->total_deductions) }}</td>
                <td class="num"><b>{{ $money($s->net) }}</b></td>
                <td><a class="btn btn-sm" href="{{ route('me.payslips.show', $s) }}" target="_blank">View / print</a></td>
            </tr>
        @empty <tr><td colspan="6" class="empty">No payslips yet. They appear here when the payroll office releases a payroll.</td></tr> @endforelse
        </tbody>
    </table></div>
    @if ($slips->isNotEmpty())
        <p style="margin-top:12px"><a class="btn btn-sm" href="{{ route('me.tax', ['year' => $year]) }}" target="_blank">Tax certificate (BIR 2316 data) {{ $year }}</a>
            @if ($year > 2000)<a class="btn btn-sm" href="{{ route('me.tax', ['year' => $year - 1]) }}" target="_blank">{{ $year - 1 }}</a>@endif</p>
    @endif
    <p class="hint">Something looks wrong? Contact the payroll office. Absences and undertime come from your DTR.</p>
</section>
@if ($loans->isNotEmpty())
<section class="card">
    <h2>My loans &amp; deductions</h2>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Deduction</th><th class="num">Per month</th><th class="num">Paid so far</th><th class="num">Balance</th><th>Status</th></tr></thead>
        <tbody>
        @foreach ($loans as $loan)
            <tr><td>{{ $loan->name }}<div class="small muted">{{ $loan->lender_name }}{{ $loan->reference_no ? ' · #'.$loan->reference_no : '' }}</div></td>
                <td class="num">{{ $money($loan->amortization) }}</td><td class="num">{{ $money($loan->paid()) }}</td>
                <td class="num">{{ $loan->balance() === null ? '—' : $money($loan->balance()) }}</td>
                <td><span @class(['badge', 'badge-ok' => $loan->status === 'active', 'badge-info' => $loan->status === 'paid'])>{{ \App\Models\Loan::STATUSES[$loan->status] }}</span></td></tr>
        @endforeach
        </tbody>
    </table></div>
    <p class="hint">Balances here count payroll deductions only. Check with the lender for payments made directly to them.</p>
</section>
@endif
@endsection
