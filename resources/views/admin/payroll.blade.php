@extends('layouts.app')
@section('title', 'Payroll')
@section('content')
@php $money = fn ($v) => number_format((float) $v, 2); @endphp
<div class="page-head">
    <div><h1>Payroll inputs — {{ $month->format('F Y') }}</h1>
        <div class="muted">Attendance-adjusted basic pay for rdcruz-ph-payroll. Post the month's leave cards first, so undertime is charged to VL before pay.</div></div>
    <form class="inline-form" method="get">
        <div class="field"><label for="month">Month</label><input type="month" id="month" name="month" value="{{ $month->format('Y-m') }}"></div>
        <button class="btn">Show</button>
    </form>
</div>
<div class="actions" style="justify-content:flex-start;margin-bottom:16px">
    <a class="btn" href="{{ route('admin.payroll.export', ['month' => $month->format('Y-m'), 'format' => 'csv']) }}">Download CSV</a>
    <a class="btn" href="{{ route('admin.payroll.export', ['month' => $month->format('Y-m'), 'format' => 'json']) }}">Download JSON (computePay inputs)</a>
    <a class="btn" href="{{ route('admin.payroll.export', ['month' => $month->format('Y-m'), 'format' => 'json', 'frequency' => 'semi-monthly']) }}">JSON, semi-monthly</a>
    <button class="btn" type="button" onclick="window.print()">Print register</button>
</div>

<section class="card table-wrap">
    <h2>Payroll register (preview)</h2>
    <table class="table" id="register">
        <thead><tr>
            <th>Employee</th><th>Basis</th><th class="num">Rate</th><th class="num">Days</th><th class="num">Deductions (attendance)</th>
            <th class="num">Basic earned</th><th class="num">GSIS</th><th class="num">PhilHealth</th><th class="num">Pag-IBIG</th><th class="num">W/tax</th><th class="num">Net</th>
        </tr></thead>
        <tbody>
        @foreach ($rows as $r)
            @continue(! $r['paid_by_agency'])
            <tr data-basic="{{ $r['monthly_basic'] }}" data-gsis="{{ $r['gsis_member'] ? 1 : 0 }}">
                <td>{{ $r['name'] }}<div class="small muted">{{ $r['type_label'] }} · {{ $r['employee_no'] }}</div>
                    @foreach ($r['notes'] as $n)<div class="small" style="color:var(--warn)">{{ $n }}</div>@endforeach</td>
                <td class="small">{{ $r['basis'] === 'daily' ? 'Daily' : 'Monthly' }}</td>
                <td class="num">{{ $money($r['rate']) }}</td>
                <td class="num small">{{ $r['basis'] === 'daily' ? ($r['days_paid'] ?? 0).' worked' : $r['days_present'].' present' }}
                    @if ($r['days_absent'])<div>{{ $r['days_absent'] }} absent</div>@endif
                    @if ($r['lwop_days'])<div>{{ $r['lwop_days'] + 0 }} LWOP</div>@endif
                    @if ($r['undertime_minutes'])<div>{{ intdiv($r['undertime_minutes'], 60) }}h{{ str_pad($r['undertime_minutes'] % 60, 2, '0', STR_PAD_LEFT) }} UT</div>@endif</td>
                <td class="num">{{ $money($r['deductions']) }}</td>
                <td class="num"><b>{{ $money($r['monthly_basic']) }}</b></td>
                <td class="num" data-c="gsis">—</td><td class="num" data-c="ph">—</td><td class="num" data-c="pi">—</td><td class="num" data-c="tax">—</td><td class="num" data-c="net"><b>—</b></td>
            </tr>
        @endforeach
        </tbody>
        <tfoot><tr><td colspan="5"><b>TOTAL</b></td><td class="num" data-t="basic"></td><td class="num" data-t="gsis"></td><td class="num" data-t="ph"></td><td class="num" data-t="pi"></td><td class="num" data-t="tax"></td><td class="num" data-t="net"></td></tr></tfoot>
    </table>
    <p class="hint">Plantilla, casual and contractual: GSIS {{ $gsis['gsis_employee'] * 100 }}% personal share; PhilHealth, Pag-IBIG and BIR withholding tax computed with rdcruz-ph-payroll (2026 rates). COS/JO: no mandatory contributions here — they remit their own SSS/PhilHealth/Pag-IBIG and the tax option in their contract (e.g. 8% or graduated); add those before release. Check every figure against your accounting before paying.</p>
</section>

@php $others = collect($rows)->where('paid_by_agency', false); @endphp
@if ($others->isNotEmpty())
<section class="card" style="margin-top:16px">
    <h2>Attendance only (paid by their own agency)</h2>
    <p class="hint">Use this for service-provider billing and the DBP attendance report.</p>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Name</th><th>Type</th><th class="num">Present</th><th class="num">OB/travel</th><th class="num">Leave</th><th class="num">Absent</th><th class="num">Undertime</th><th class="num">OT credited</th></tr></thead><tbody>
        @foreach ($others as $r)
            <tr><td>{{ $r['name'] }}</td><td class="small">{{ $r['type_label'] }}@foreach ($r['notes'] as $n)<div class="muted">{{ $n }}</div>@endforeach</td><td class="num">{{ $r['days_present'] }}</td><td class="num">{{ $r['travel_days'] }}</td><td class="num">{{ $r['leave_days'] + 0 }}</td>
                <td class="num">{{ $r['days_absent'] }}</td><td class="num">{{ intdiv($r['undertime_minutes'], 60) }}h{{ str_pad($r['undertime_minutes'] % 60, 2, '0', STR_PAD_LEFT) }}</td><td class="num">{{ round($r['overtime_credited_minutes'] / 60, 2) }} h</td></tr>
        @endforeach
        </tbody></table></div>
</section>
@endif
@endsection
@push('scripts')
<script type="module">
import { philhealth, pagibig, withholdingTax } from '{{ asset('vendor/rdcruz-ph-payroll/payroll.js') }}';
const GSIS = {{ $gsis['gsis_employee'] }};
const r2 = (x) => Math.round(x * 100) / 100;
const fmt = (x) => x.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const totals = { basic: 0, gsis: 0, ph: 0, pi: 0, tax: 0, net: 0 };
document.querySelectorAll('#register tbody tr').forEach((tr) => {
  const basic = parseFloat(tr.dataset.basic) || 0;
  let gsis = 0, ph = 0, pi = 0, tax = 0;
  if (tr.dataset.gsis === '1' && basic > 0) {
    gsis = r2(basic * GSIS);
    ph = philhealth(basic).employee;
    pi = pagibig(basic).employee;
    tax = withholdingTax(r2(basic - gsis - ph - pi), 'monthly');
  }
  const net = r2(basic - gsis - ph - pi - tax);
  const set = (k, v) => { tr.querySelector(`[data-c="${k}"]`).innerHTML = k === 'net' ? `<b>${fmt(v)}</b>` : fmt(v); totals[k] += v; };
  set('gsis', gsis); set('ph', ph); set('pi', pi); set('tax', tax); set('net', net); totals.basic += basic;
});
Object.entries(totals).forEach(([k, v]) => { const td = document.querySelector(`[data-t="${k}"]`); if (td) td.innerHTML = `<b>${fmt(v)}</b>`; });
</script>
@endpush
