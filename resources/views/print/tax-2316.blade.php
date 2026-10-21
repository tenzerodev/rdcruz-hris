@extends('print._layout')
@section('title', 'BIR Form 2316 data — '.$year.($rows->count() === 1 ? ' — '.$rows->first()['employee']->full_name : ''))
@section('content')
@php $money = fn ($v) => number_format((float) $v, 2); @endphp
@forelse ($rows as $r)
    @php $e = $r['employee']; @endphp
    <div style="{{ $loop->last ? '' : 'page-break-after:always;break-after:page;' }}">
        <x-print-header />
        <h1>CERTIFICATE OF COMPENSATION PAYMENT / TAX WITHHELD</h1>
        <p class="c small">Data for BIR Form No. 2316 · For the year {{ $year }} (January 1 – December 31)</p>
        <table class="f" style="margin-top:4mm">
            <tr><td style="width:40%"><div class="lbl">Employee</div></td><td class="val">{{ $e->display_name }}</td></tr>
            <tr><td><div class="lbl">Employee TIN</div></td><td class="val">{{ $e->tin ?: '—' }}</td></tr>
            <tr><td><div class="lbl">Employer</div></td><td class="val">{{ $settings['agency_name'] }}{{ $settings['agency_address'] ? ', '.$settings['agency_address'] : '' }}</td></tr>
            <tr><td><div class="lbl">Employer TIN</div></td><td class="val">{{ $settings['agency_tin'] ?: '—' }}</td></tr>
        </table>
        <table class="f" style="margin-top:4mm">
            <tr><th colspan="2" style="text-align:left">Summary</th></tr>
            <tr><td>Gross compensation income from present employer</td><td style="text-align:right">{{ $money($r['gross']) }}</td></tr>
            <tr><td>Less: non-taxable / exempt compensation</td><td></td></tr>
            <tr><td style="padding-left:8mm">13th month pay and other benefits (up to ₱{{ number_format(config('hris.payroll.benefits_exemption')) }})</td><td style="text-align:right">{{ $money($r['benefits_exempt']) }}</td></tr>
            <tr><td style="padding-left:8mm">De minimis and other non-taxable pay (e.g. PERA)</td><td style="text-align:right">{{ $money($r['other_non_taxable']) }}</td></tr>
            <tr><td style="padding-left:8mm">GSIS, PhilHealth, Pag-IBIG contributions (employee share)</td><td style="text-align:right">{{ $money($r['contributions']) }}</td></tr>
            <tr><td><b>Taxable compensation income</b> (incl. ₱{{ $money($r['benefits_taxable']) }} benefits above the exemption)</td><td style="text-align:right"><b>{{ $money($r['taxable']) }}</b></td></tr>
            <tr><td>Tax due</td><td style="text-align:right">{{ $money($r['tax_due']) }}</td></tr>
            <tr><td>Tax withheld from January to December</td><td style="text-align:right">{{ $money($r['withheld']) }}</td></tr>
            <tr><td><b>{{ $r['difference'] >= 0 ? 'Tax still to be withheld' : 'Tax to be refunded' }}</b></td><td style="text-align:right"><b>{{ $money(abs($r['difference'])) }}</b></td></tr>
        </table>
        <p class="small" style="margin-top:4mm">From {{ $r['slips'] }} released payslip(s). Compensation from previous employers in the same year is not included. The official BIR Form No. 2316 is issued and signed by the employer and the employee.</p>
        <div style="display:flex;gap:12mm;margin-top:16mm">
            <div class="sign" style="flex:1"><div class="who">{{ $settings['hr_head_name'] ?? '' }}</div><span class="n">Authorized representative of the employer</span></div>
            <div class="sign" style="flex:1"><div class="who">{{ $e->display_name }}</div><span class="n">Employee</span></div>
        </div>
        <x-verify-stamp type="tax_2316" :key="'2316:'.$e->id.':'.$year.':'.md5(json_encode([$r['gross'], $r['withheld']]))" :employee="$e" :title="'Compensation and tax withheld '.$year.' — '.$e->full_name" :summary="[
            'Employee' => $e->full_name, 'Year' => $year, 'Gross compensation' => '₱'.$money($r['gross']), 'Taxable compensation' => '₱'.$money($r['taxable']), 'Tax withheld' => '₱'.$money($r['withheld']),
        ]" style="margin-top:8mm" />
    </div>
@empty
    <p>No released payslips for {{ $year }}.</p>
@endforelse
@endsection
