@extends('print._layout')
@section('title', $slips->count() === 1 ? 'Payslip — '.$slips->first()->employee->full_name.' — '.$slips->first()->run->label : 'Payslips — '.($slips->first()?->run->label ?? ''))
@section('content')
@php $money = fn ($v) => number_format((float) $v, 2); @endphp
<style>
    .slip { page-break-inside: avoid; break-inside: avoid; border: 1px solid #000; padding: 5mm 6mm; margin-bottom: 6mm; }
    .slip + .slip { page-break-before: auto; }
    .slip h1 { font-size: 13pt; margin: 2mm 0 1mm; }
    .slip table.lines { width: 100%; border-collapse: collapse; font-size: 9.5pt; }
    .slip table.lines td { padding: 1mm 0; border-bottom: 1px dotted #999; }
    .slip table.lines td.a { text-align: right; white-space: nowrap; width: 30mm; font-variant-numeric: tabular-nums; }
    .slip table.lines tr.t td { border-top: 1px solid #000; border-bottom: 0; font-weight: bold; }
    .slip .cols { display: flex; gap: 8mm; margin-top: 3mm; }
    .slip .cols > div { flex: 1; }
    .slip .net { margin-top: 3mm; border: 2px solid #000; padding: 2mm 4mm; display: flex; justify-content: space-between; font-size: 12pt; font-weight: bold; }
    .slip .meta { font-size: 9pt; display: grid; grid-template-columns: 1fr 1fr; gap: 1mm 6mm; margin-top: 2mm; }
    .slip .small-note { font-size: 7.5pt; color: #333; margin-top: 2mm; }
    .draft-mark { text-align: center; font: bold 11pt Arial, sans-serif; color: #b91c1c; border: 1px dashed #b91c1c; padding: 1mm; margin-bottom: 2mm; }
</style>
@foreach ($slips as $s)
    @php
        $e = $s->employee;
        $in = $s->inputs;
        $ytd = $s->yearToDate();
    @endphp
    <div class="slip">
        @unless ($s->run->isReleased())<div class="draft-mark">DRAFT — NOT YET RELEASED</div>@endunless
        <x-print-header compact />
        <h1 class="c">PAYSLIP</h1>
        <p class="c small" style="margin:0">Pay period: <b>{{ $s->run->label }}</b>{{ $s->run->pay_date ? ' · Pay date: '.$s->run->pay_date->format('F j, Y') : '' }}</p>
        <div class="meta">
            <div>Name: <b>{{ $e->display_name }}</b></div>
            <div>Employee no.: <b>{{ $e->employee_no }}</b></div>
            <div>Position: {{ $in['position'] ?? $e->position }}{{ ! empty($in['salary_grade']) ? ' (SG '.$in['salary_grade'].($in['step'] ? '-'.$in['step'] : '').')' : '' }}</div>
            <div>Office: {{ $in['office'] ?? $e->office }}</div>
            <div>Status: {{ $in['type_label'] ?? $e->type_label }}</div>
            @if (! empty($in['bonus']))
            <div>Eligibility cut-off: {{ \Carbon\Carbon::parse($in['cutoff'])->format('F j, Y') }}</div>
            @else
            <div>Attendance (month): {{ $in['days_present'] ?? 0 }} present{{ ! empty($in['days_absent']) ? ', '.$in['days_absent'].' absent' : '' }}{{ ! empty($in['lwop_days']) ? ', '.($in['lwop_days'] + 0).' LWOP' : '' }}{{ ! empty($in['undertime_minutes']) ? ', '.intdiv($in['undertime_minutes'], 60).'h'.str_pad($in['undertime_minutes'] % 60, 2, '0', STR_PAD_LEFT).' undertime' : '' }}</div>
            @endif
        </div>
        <div class="cols">
            <div>
                <table class="lines"><tbody>
                    <tr><td colspan="2"><b>EARNINGS</b></td></tr>
                    @foreach ($s->earnings as $l)<tr><td>{{ $l['label'] }}{{ empty($l['taxable']) ? ' (non-taxable)' : '' }}</td><td class="a">{{ $money($l['amount']) }}</td></tr>@endforeach
                    <tr class="t"><td>Gross pay</td><td class="a">{{ $money($s->gross) }}</td></tr>
                </tbody></table>
            </div>
            <div>
                <table class="lines"><tbody>
                    <tr><td colspan="2"><b>DEDUCTIONS</b></td></tr>
                    @forelse ($s->deductions as $l)<tr><td>{{ $l['label'] }}</td><td class="a">{{ $money($l['amount']) }}</td></tr>@empty<tr><td>None</td><td class="a">0.00</td></tr>@endforelse
                    <tr class="t"><td>Total deductions</td><td class="a">{{ $money($s->total_deductions) }}</td></tr>
                </tbody></table>
            </div>
        </div>
        <div class="net"><span>NET PAY</span><span>₱ {{ $money($s->net) }}</span></div>
        <div class="meta" style="margin-top:3mm">
            <div>Taxable compensation this period: {{ $money($s->taxable) }}</div>
            <div>Year to date ({{ substr($s->run->period, 0, 4) }}): gross {{ $money($ytd['gross']) }} · tax withheld {{ $money($ytd['tax']) }}</div>
            @if ($s->employer_share)<div>Employer share: {{ collect($s->employer_share)->map(fn ($v, $k) => $k.' '.$money($v))->join(' · ') }}</div>@endif
        </div>
        @if ($s->run->isReleased())
            <x-verify-stamp compact type="payslip" :key="'payslip:'.$s->id.':'.$s->updated_at?->timestamp" :employee="$e" :title="'Payslip '.$s->run->label.' — '.$e->full_name" :summary="[
                'Employee' => $e->full_name, 'Pay period' => $s->run->label, 'Gross pay' => '₱'.$money($s->gross), 'Net pay' => '₱'.$money($s->net),
            ]" />
        @endif
        @if (! empty($in['bonus']))<p class="small-note">Bonuses and the cash gift are tax-exempt up to ₱{{ number_format(config('hris.payroll.benefits_exemption')) }} a year together with other benefits; any excess is taxed in the year-end adjustment.</p>@endif
        <p class="small-note">This is a system-generated payslip. Contributions and tax follow the 2026 GSIS, PhilHealth, Pag-IBIG and BIR rates. Questions: contact the payroll office.</p>
    </div>
@endforeach
@endsection
