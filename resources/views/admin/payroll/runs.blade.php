@extends('layouts.app')
@section('title', 'Payroll & payslips')
@section('content')
@php $money = fn ($v) => '₱'.number_format((float) $v, 2); @endphp
<div class="page-head">
    <div><h1>Payroll &amp; payslips</h1><div class="muted">Make a draft payroll from attendance, check and adjust it, then release it. Employees see their payslips only after release.</div></div>
    <a class="btn" href="{{ route('admin.payroll.index') }}">Payroll inputs</a>
</div>

<div class="grid grid-2" style="margin-bottom:16px">
    <section class="card">
        <h2>New payroll</h2>
        @if (! $readOnly)
        <form method="post" action="{{ route('admin.payroll.runs.store') }}">
            @csrf
            <div class="row">
                <div class="field"><label for="period">Month</label><input type="month" id="period" name="period" value="{{ old('period', now()->format('Y-m')) }}" required></div>
                <div class="field"><label for="cut">Pay period</label><select id="cut" name="cut">@foreach (\App\Models\PayrollRun::CUTS as $k => $l)<option value="{{ $k }}" @selected(old('cut') === $k)>{{ $l }}</option>@endforeach</select></div>
                <div class="field"><label for="pay_date">Pay date</label><input type="date" id="pay_date" name="pay_date" value="{{ old('pay_date') }}"></div>
            </div>
            <div class="field"><label for="notes">Notes</label><input type="text" id="notes" name="notes" value="{{ old('notes') }}"></div>
            <button class="btn btn-primary">Make draft payroll</button>
        </form>
        @else
            <p class="muted">Your role can view payrolls but not make them.</p>
        @endif
    </section>
    <section class="card">
        <h2>Before you start</h2>
        <ol class="steps">
            <li>Finish the month's attendance corrections and <a href="{{ route('admin.leave.posting') }}">post the leave cards</a>, so undertime is charged to leave first.</li>
            <li>Make the draft. Basic pay, absences, GSIS, PhilHealth, Pag-IBIG, PERA and withholding tax are filled in.</li>
            <li>Add loans, allowances or other items per employee, then <b>Release</b>.</li>
        </ol>
        <p class="hint">Semi-monthly: each half gets half of the month's pay, contributions and tax. Check every figure against accounting before paying.</p>
    </section>
</div>

<section class="card">
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Period</th><th>Status</th><th class="num">Payslips</th><th class="num">Net pay total</th><th>Pay date</th><th></th></tr></thead>
        <tbody>
        @forelse ($runs as $r)
            <tr>
                <td><a href="{{ route('admin.payroll.runs.show', $r) }}"><b>{{ $r->label }}</b></a>@if ($r->notes)<div class="small muted">{{ $r->notes }}</div>@endif</td>
                <td>@if ($r->isReleased())<span class="badge badge-ok">Released</span><div class="small muted">{{ $r->released_at->format('M j, Y') }}</div>@else<span class="badge badge-warn">Draft</span>@endif</td>
                <td class="num">{{ $r->payslips_count }}</td>
                <td class="num">{{ $money($r->payslips_sum_net) }}</td>
                <td>{{ $r->pay_date?->format('M j, Y') ?? '—' }}</td>
                <td><a class="btn btn-sm" href="{{ route('admin.payroll.runs.show', $r) }}">Open</a></td>
            </tr>
        @empty <tr><td colspan="6" class="empty">No payrolls yet.</td></tr> @endforelse
        </tbody>
    </table></div>
    <div class="pagination">{{ $runs->links() }}</div>
</section>
@endsection
