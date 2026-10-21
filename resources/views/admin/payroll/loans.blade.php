@extends('layouts.app')
@section('title', 'Loans & deductions')
@section('content')
@php $money = fn ($v) => number_format((float) $v, 2); @endphp
<div class="page-head">
    <div><h1>Loans &amp; recurring deductions</h1><div class="muted">Deducted automatically from every regular payslip until fully paid, stopped, or the end month. Payments are recorded when a payroll is released.</div></div>
    <x-import-button key="loans" />
</div>
<nav class="tabs">
    @foreach (['active' => 'Active', 'paid' => 'Fully paid', 'stopped' => 'Stopped', 'all' => 'All'] as $k => $l)
        <a href="{{ route('admin.loans.index', ['status' => $k] + request()->only('q', 'lender')) }}" @if ($status === $k) aria-current="page" @endif>{{ $l }}</a>
    @endforeach
</nav>
<div class="grid" style="grid-template-columns: minmax(0, 2fr) minmax(280px, 1fr)">
    <section class="card">
        <form method="get" class="row" style="align-items:flex-end">
            <input type="hidden" name="status" value="{{ $status }}">
            <div class="field"><label for="q">Search</label><input type="search" id="q" name="q" value="{{ request('q') }}" placeholder="Name, employee no., loan, reference"></div>
            <div class="field"><label for="lender">Lender</label><select id="lender" name="lender"><option value="">All</option>@foreach (\App\Models\Loan::lenders() as $k => $l)<option value="{{ $k }}" @selected(request('lender') === $k)>{{ $l }}</option>@endforeach</select></div>
            <div class="field"><button class="btn">Filter</button></div>
        </form>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Employee</th><th>Deduction</th><th class="num">Per month</th><th class="num">Paid</th><th class="num">Balance</th><th>Months</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse ($loans as $loan)
                <tr>
                    <td><b>{{ $loan->employee->full_name }}</b><div class="small muted">{{ $loan->employee->employee_no }}</div></td>
                    <td>{{ $loan->name }}<div class="small muted">{{ $loan->lender_name }}{{ $loan->reference_no ? ' · #'.$loan->reference_no : '' }}{{ $loan->notes ? ' · '.$loan->notes : '' }}</div></td>
                    <td class="num">{{ $money($loan->amortization) }}</td>
                    <td class="num">{{ $money($loan->paid()) }}</td>
                    <td class="num">{{ $loan->balance() === null ? '—' : $money($loan->balance()) }}@if ($loan->total_amount)<div class="small muted">of {{ $money($loan->total_amount) }}</div>@endif</td>
                    <td class="small">{{ \Carbon\Carbon::createFromFormat('Y-m', $loan->start_period)->format('M Y') }}{{ $loan->end_period ? ' – '.\Carbon\Carbon::createFromFormat('Y-m', $loan->end_period)->format('M Y') : ' onward' }}</td>
                    <td><span @class(['badge', 'badge-ok' => $loan->status === 'active', 'badge-info' => $loan->status === 'paid'])>{{ \App\Models\Loan::STATUSES[$loan->status] }}</span></td>
                    <td>@unless ($readOnly)<div class="actions">
                        @if ($loan->status === 'active')
                            <form method="post" action="{{ route('admin.loans.update', $loan) }}">@csrf @method('put')<input type="hidden" name="status" value="stopped"><button class="btn btn-sm">Stop</button></form>
                        @elseif ($loan->status === 'stopped')
                            <form method="post" action="{{ route('admin.loans.update', $loan) }}">@csrf @method('put')<input type="hidden" name="status" value="active"><button class="btn btn-sm">Resume</button></form>
                        @endif
                        <details class="inline-form"><summary class="btn btn-sm">Edit</summary>
                            <form method="post" action="{{ route('admin.loans.update', $loan) }}" class="card" style="margin-top:8px;min-width:260px">@csrf @method('put')
                                @include('admin.payroll._loan-fields', ['loan' => $loan])
                                <button class="btn btn-primary btn-sm">Save</button>
                            </form>
                        </details>
                        @if (! $loan->paid())
                            <form method="post" action="{{ route('admin.loans.destroy', $loan) }}" onsubmit="return confirm('Delete this deduction?')">@csrf @method('delete')<button class="btn btn-sm btn-danger">Delete</button></form>
                        @endif
                    </div>@endunless</td>
                </tr>
            @empty <tr><td colspan="8" class="empty">No deductions here.</td></tr> @endforelse
            </tbody>
        </table></div>
        <div class="pagination">{{ $loans->links() }}</div>
        <p class="hint">Active deductions total ₱{{ $money($monthly) }} a month. Semi-monthly payrolls take {{ config('hris.payroll.loan_deduction') === 'second' ? 'the whole amount on the 2nd half' : 'half each pay day' }}. The last payment is reduced to the remaining balance.</p>
    </section>
    <section class="card">
        <h2>Add a loan or deduction</h2>
        @if ($readOnly)
            <p class="muted">Your role can view loans but not change them.</p>
        @else
        <form method="post" action="{{ route('admin.loans.store') }}">@csrf
            <div class="field"><label for="employee_id">Employee</label><select id="employee_id" name="employee_id" required><option value=""></option>
                @foreach ($employees as $e)<option value="{{ $e->id }}" @selected(old('employee_id') == $e->id)>{{ $e->full_name }} ({{ $e->employee_no }})</option>@endforeach</select></div>
            @include('admin.payroll._loan-fields', ['loan' => null])
            <button class="btn btn-primary">Add</button>
        </form>
        @endif
    </section>
</div>
@endsection
