@extends('layouts.app')
@section('title', 'Monetization & terminal leave')
@section('content')
@php
    $money = fn ($v) => '₱'.number_format((float) $v, 2);
    $n = fn ($v) => rtrim(rtrim(number_format((float) $v, 3), '0'), '.') ?: '0';
@endphp
<div class="page-head">
    <div><h1>Leave monetization &amp; terminal leave</h1><div class="muted">Amount = monthly salary × days × {{ $factor }} (CSC constant factor). The days are charged to the leave card.</div></div>
</div>
@if (session('print'))<div class="alert alert-ok"><x-icon name="file" /><div>Computation ready. <a href="{{ session('print') }}" target="_blank">Print it</a>.</div></div>@endif

<section class="card" style="margin-bottom:16px">
    <form class="inline-form" method="get">
        <div class="field" style="min-width:280px"><label for="employee_id">Employee</label><select id="employee_id" name="employee_id" onchange="this.form.submit()"><option value="">— Choose —</option>
            @foreach ($employees as $e)<option value="{{ $e->id }}" @selected($selected?->id === $e->id)>{{ $e->full_name }}{{ $e->is_active ? '' : ' (separated)' }}</option>@endforeach</select></div>
        <noscript><button class="btn">Show</button></noscript>
    </form>
    @if ($selected)
        <p style="margin-top:12px">Balances: <b>VL {{ $n($balances['VL']) }}</b> · <b>SL {{ $n($balances['SL']) }}</b> · Monthly salary {{ $money($selected->monthly_salary) }}</p>
        <div class="grid grid-2">
            <div>
                <h2>Monetize</h2>
                <form method="post" action="{{ route('admin.leave.monetization.store') }}" data-calc data-salary="{{ (float) $selected->monthly_salary }}" data-factor="{{ $factor }}">
                    @csrf <input type="hidden" name="employee_id" value="{{ $selected->id }}">
                    <div class="row">
                        <div class="field"><label for="kind">Kind</label><select id="kind" name="kind">
                            <option value="regular">Regular (10+ days VL, keep 5)</option><option value="special">Special (50% or more, with reason)</option></select></div>
                        <div class="field"><label for="date">Date</label><input type="date" id="date" name="date" value="{{ today()->toDateString() }}" required></div>
                    </div>
                    <div class="row">
                        <div class="field"><label for="vl_days">VL days</label><input type="number" step="0.001" min="0" id="vl_days" name="vl_days" value="10"></div>
                        <div class="field"><label for="sl_days">SL days</label><input type="number" step="0.001" min="0" id="sl_days" name="sl_days" value="0"></div>
                    </div>
                    <div class="field"><label for="reason">Reason (required for special)</label><input type="text" id="reason" name="reason" placeholder="e.g. hospitalization of dependent"></div>
                    <p>Amount: <b data-out>—</b></p>
                    @unless ($readOnly)<button class="btn btn-primary">Record monetization</button>@endunless
                </form>
            </div>
            <div>
                <h2>Terminal leave benefit</h2>
                <table class="table"><tbody>
                    <tr><td>Vacation + sick leave credits</td><td class="num">{{ $n($terminal['vl']) }} + {{ $n($terminal['sl']) }} = <b>{{ $n($terminal['days']) }}</b> days</td></tr>
                    <tr><td>Highest monthly salary received</td><td class="num">{{ $money($terminal['salary']) }}</td></tr>
                    <tr><td><b>Terminal leave benefit</b></td><td class="num"><b>{{ $money($terminal['amount']) }}</b></td></tr>
                </tbody></table>
                <p class="hint">For retirement, resignation or separation not for cause. Recording it uses up all VL and SL on the leave card. Deduct any monetization already paid this year if your rules require it.</p>
                @unless ($readOnly)
                    <form method="post" action="{{ route('admin.leave.monetization.store') }}" onsubmit="return confirm('Record the terminal leave benefit of {{ $money($terminal['amount']) }}? All VL and SL are used up.')">
                        @csrf <input type="hidden" name="employee_id" value="{{ $selected->id }}"><input type="hidden" name="kind" value="terminal">
                        <div class="row"><div class="field"><label for="tdate">Separation date</label><input type="date" id="tdate" name="date" value="{{ $selected->date_separated?->toDateString() ?? today()->toDateString() }}" required></div></div>
                        <button class="btn">Record terminal leave</button>
                    </form>
                @endunless
            </div>
        </div>
    @endif
</section>

<section class="card">
    <h2>Recorded</h2>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Date</th><th>Employee</th><th>Kind</th><th class="num">Days</th><th class="num">Amount</th><th></th></tr></thead><tbody>
        @forelse ($records as $m)
            <tr><td>{{ $m->date->format('M j, Y') }}</td><td>{{ $m->employee->full_name }}</td><td>{{ $m->kind_name }}@if ($m->reason)<div class="small muted">{{ $m->reason }}</div>@endif</td>
                <td class="num">{{ $n($m->vl_days + $m->sl_days) }}</td><td class="num">{{ $money($m->amount) }}</td>
                <td><div class="actions"><a class="btn btn-sm" href="{{ route('admin.leave.monetization.print', $m) }}" target="_blank">Print</a>
                    @unless ($readOnly)<form method="post" action="{{ route('admin.leave.monetization.destroy', $m) }}" onsubmit="return confirm('Cancel this and restore the leave credits?')">@csrf @method('delete')<button class="btn btn-sm btn-danger">Cancel</button></form>@endunless</div></td></tr>
        @empty <tr><td colspan="6" class="empty">None yet.</td></tr> @endforelse
        </tbody></table></div>
    <div class="pagination">{{ $records->links() }}</div>
</section>
<script>
(function () {
  var f = document.querySelector('[data-calc]'); if (!f) return;
  function calc() {
    var d = (parseFloat(f.vl_days.value) || 0) + (parseFloat(f.sl_days.value) || 0);
    var a = Math.round(parseFloat(f.dataset.salary) * d * parseFloat(f.dataset.factor) * 100) / 100;
    f.querySelector('[data-out]').textContent = '₱' + a.toLocaleString('en-PH', { minimumFractionDigits: 2 });
  }
  f.addEventListener('input', calc); calc();
})();
</script>
@endsection
