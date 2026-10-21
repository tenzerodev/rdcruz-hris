@extends('layouts.app')
@section('title', 'Adjust payslip')
@section('content')
@php
    $money = fn ($v) => number_format((float) $v, 2);
    $earn = $slip->manualEarnings();
    $ded = $slip->manualDeductions();
@endphp
<div class="page-head">
    <div><h1>Adjust payslip</h1><div class="muted">{{ $slip->employee->full_name }} · {{ $slip->run->label }}</div></div>
    <a class="btn" href="{{ route('admin.payroll.runs.show', $slip->run) }}">Back</a>
</div>
<div class="grid grid-2">
    <section class="card">
        <h2>Computed from attendance</h2>
        <table class="table"><tbody>
            @foreach ($slip->earnings as $l)@continue(! empty($l['manual']))<tr><td>{{ $l['label'] }}</td><td class="num">{{ $money($l['amount']) }}</td></tr>@endforeach
            @foreach ($slip->deductions as $l)@continue(! empty($l['manual']))<tr><td>{{ $l['label'] }}@if (($l['kind'] ?? null) === 'loan')<div class="small muted">From <a href="{{ route('admin.loans.index', ['q' => $slip->employee->employee_no]) }}">Loans &amp; deductions</a></div>@endif</td><td class="num">−{{ $money($l['amount']) }}</td></tr>@endforeach
            <tr><td><b>Net pay now</b></td><td class="num"><b>{{ $money($slip->net) }}</b></td></tr>
        </tbody></table>
        <p class="hint">To change these, correct the DTR or leave and use "Recompute from attendance". Withholding tax is recomputed when you add taxable pay.</p>
    </section>
    <section class="card">
        <h2>Add items for this pay period</h2>
        <form method="post" action="{{ route('admin.payslips.update', $slip) }}">
            @csrf @method('put')
            <h3 class="small muted" style="margin:0 0 6px">Other pay</h3>
            @for ($i = 0; $i < max(3, count($earn) + 1); $i++)
                <div class="row" style="align-items:flex-end">
                    <div class="field" style="flex:2"><label for="e{{ $i }}l" class="sr">Label</label><input type="text" id="e{{ $i }}l" name="earnings[{{ $i }}][label]" value="{{ $earn[$i]['label'] ?? '' }}" placeholder="e.g. Honorarium"></div>
                    <div class="field"><label for="e{{ $i }}a" class="sr">Amount</label><input type="number" step="0.01" min="0" id="e{{ $i }}a" name="earnings[{{ $i }}][amount]" value="{{ $earn[$i]['amount'] ?? '' }}" placeholder="0.00"></div>
                    <label class="check" style="margin-bottom:12px"><input type="checkbox" name="earnings[{{ $i }}][taxable]" value="1" @checked($earn[$i]['taxable'] ?? true)> Taxable</label>
                </div>
            @endfor
            <h3 class="small muted" style="margin:8px 0 6px">Other deductions</h3>
            @for ($i = 0; $i < max(4, count($ded) + 1); $i++)
                <div class="row">
                    <div class="field" style="flex:2"><label for="d{{ $i }}l" class="sr">Label</label><input type="text" id="d{{ $i }}l" name="deductions[{{ $i }}][label]" value="{{ $ded[$i]['label'] ?? '' }}" placeholder="e.g. GSIS Multi-Purpose Loan"></div>
                    <div class="field"><label for="d{{ $i }}a" class="sr">Amount</label><input type="number" step="0.01" min="0" id="d{{ $i }}a" name="deductions[{{ $i }}][amount]" value="{{ $ded[$i]['amount'] ?? '' }}" placeholder="0.00"></div>
                </div>
            @endfor
            <p class="hint">Amounts are for this pay period. Clear a row to remove it.</p>
            <button class="btn btn-primary">Save and recompute</button>
        </form>
    </section>
</div>
@endsection
