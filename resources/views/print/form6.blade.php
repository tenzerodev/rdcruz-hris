@extends('print._layout')
@section('title', 'Application for Leave — '.$leave->employee->full_name)
@section('content')
@php
    $e = $leave->employee;
    $x = fn (bool $on) => '<span class="box">'.($on ? '✓' : '').'</span>';
    $d = $leave->details ?? [];
    $charge = config("hris.leave_types.{$leave->type}.charge");
    // 7.A certification: what this application takes from VL/SL.
    $cert = [];
    if ($balances) {
        foreach (['VL', 'SL'] as $b) {
            $less = $charge === $b ? ($leave->status === 'approved' ? (float) $leave->days_with_pay : (float) $leave->days) : 0;
            $current = $balances[$b];
            $cert[$b] = $leave->status === 'approved'
                ? ['earned' => $current + $less, 'less' => $less, 'balance' => $current]
                : ['earned' => $current, 'less' => $less, 'balance' => $current - $less];
        }
    }
    $n = fn ($v) => number_format((float) $v, 3);
    $types = config('hris.leave_types');
@endphp
<div class="formno">Civil Service Form No. 6<br>Revised 2020</div>
<x-print-header />
<h1>APPLICATION FOR LEAVE</h1>
<table class="f">
    <tr><td style="width:35%"><div class="lbl">1. Office / Department</div><div class="val">{{ $e->office }}</div></td>
        <td colspan="2"><div class="lbl">2. Name &nbsp; (Last) &nbsp; (First) &nbsp; (Middle)</div><div class="val">{{ mb_strtoupper($e->last_name) }}, {{ $e->first_name }} {{ $e->middle_name }} {{ $e->suffix }}</div></td></tr>
    <tr><td><div class="lbl">3. Date of filing</div><div class="val">{{ $leave->created_at->format('F j, Y') }}</div></td>
        <td><div class="lbl">4. Position</div><div class="val">{{ $e->position }}</div></td>
        <td style="width:25%"><div class="lbl">5. Salary</div><div class="val">{{ $e->monthly_salary ? number_format($e->monthly_salary, 2) : '' }}</div></td></tr>
    <tr><td colspan="3" class="sec">6. DETAILS OF APPLICATION</td></tr>
    <tr>
        <td colspan="2" style="width:55%"><div class="lbl">6.A Type of leave to be availed of</div>
            @foreach ($types as $code => $t)
                <div class="opt">{!! $x($leave->type === $code) !!}{{ $t['name'] }} @if ($t['basis'])<span class="basis">({{ $t['basis'] }})</span>@endif
                    @if ($code === 'OTHERS' && $leave->type === 'OTHERS') <span class="line">{{ $d['other'] ?? '' }}</span>@endif</div>
            @endforeach
        </td>
        <td><div class="lbl">6.B Details of leave</div>
            <div class="small"><i>In case of Vacation/Special Privilege Leave:</i></div>
            <div class="opt">{!! $x(in_array($leave->type, ['VL', 'FL', 'SPL']) && ($d['location'] ?? 'ph') === 'ph') !!}Within the Philippines</div>
            <div class="opt">{!! $x(($d['location'] ?? '') === 'abroad') !!}Abroad (specify) <span class="line" style="min-width:20mm">{{ $d['abroad_where'] ?? '' }}</span></div>
            <div class="small" style="margin-top:2mm"><i>In case of Sick Leave:</i></div>
            <div class="opt">{!! $x(($d['sick'] ?? '') === 'hospital') !!}In Hospital (specify illness) <span class="line" style="min-width:15mm">{{ ($d['sick'] ?? '') === 'hospital' ? ($d['illness'] ?? '') : '' }}</span></div>
            <div class="opt">{!! $x(($d['sick'] ?? '') === 'outpatient') !!}Out Patient (specify illness) <span class="line" style="min-width:15mm">{{ ($d['sick'] ?? '') === 'outpatient' ? ($d['illness'] ?? '') : '' }}</span></div>
            <div class="small" style="margin-top:2mm"><i>In case of Special Leave Benefits for Women:</i></div>
            <div class="opt">(Specify illness) <span class="line" style="min-width:25mm">{{ $d['women_illness'] ?? '' }}</span></div>
            <div class="small" style="margin-top:2mm"><i>In case of Study Leave:</i></div>
            <div class="opt">{!! $x(($d['study'] ?? '') === 'masters') !!}Completion of Master's Degree</div>
            <div class="opt">{!! $x(($d['study'] ?? '') === 'bar_board') !!}BAR/Board Examination Review</div>
            <div class="small" style="margin-top:2mm"><i>Other purpose:</i></div>
            <div class="opt">{!! $x($leave->commutation && $leave->type === 'OTHERS') !!}Monetization of Leave Credits</div>
            <div class="opt">{!! $x(false) !!}Terminal Leave</div>
        </td>
    </tr>
    <tr>
        <td colspan="2"><div class="lbl">6.C Number of working days applied for</div><div class="val">{{ rtrim(rtrim(number_format($leave->days, 2), '0'), '.') }} day(s)</div>
            <div class="lbl" style="margin-top:2mm">Inclusive dates</div>
            <div class="val">{{ $leave->date_from->format('F j, Y') }}{{ $leave->date_to->ne($leave->date_from) ? ' – '.$leave->date_to->format('F j, Y') : '' }}{{ $leave->half_day ? ' ('.strtoupper($leave->half_day).')' : '' }}</div></td>
        <td><div class="lbl">6.D Commutation</div>
            <div class="opt">{!! $x(! $leave->commutation) !!}Not Requested</div>
            <div class="opt">{!! $x($leave->commutation) !!}Requested</div>
            <div class="sign"><div class="who">{{ $e->display_name }}</div><span class="n">(Signature of Applicant)</span></div></td>
    </tr>
    <tr><td colspan="3" class="sec">7. DETAILS OF ACTION ON APPLICATION</td></tr>
    <tr>
        <td colspan="2"><div class="lbl">7.A Certification of leave credits</div>
            <div class="small c">As of <span class="line" style="min-width:30mm">{{ ($leave->approved_at ?? now())->format('F j, Y') }}</span></div>
            <table class="f" style="margin-top:2mm">
                <tr><th></th><th class="small">Vacation Leave</th><th class="small">Sick Leave</th></tr>
                <tr><td class="small"><i>Total Earned</i></td><td class="c">{{ $cert ? $n($cert['VL']['earned']) : '' }}</td><td class="c">{{ $cert ? $n($cert['SL']['earned']) : '' }}</td></tr>
                <tr><td class="small"><i>Less this application</i></td><td class="c">{{ $cert ? $n($cert['VL']['less']) : '' }}</td><td class="c">{{ $cert ? $n($cert['SL']['less']) : '' }}</td></tr>
                <tr><td class="small"><i>Balance</i></td><td class="c">{{ $cert ? $n($cert['VL']['balance']) : '' }}</td><td class="c">{{ $cert ? $n($cert['SL']['balance']) : '' }}</td></tr>
            </table>
            <div class="sign"><div class="who">&nbsp;</div><span class="n">(Authorized Officer)</span></div></td>
        <td><div class="lbl">7.B Recommendation</div>
            <div class="opt">{!! $x(in_array($leave->status, ['recommended', 'approved'])) !!}For approval</div>
            <div class="opt">{!! $x($leave->status === 'disapproved' && $leave->recommended_by && ! $leave->approved_by) !!}For disapproval due to</div>
            <div class="line" style="width:100%;min-height:5mm">{{ $leave->status === 'disapproved' && ! $leave->approved_by ? $leave->remarks : '' }}</div>
            <div class="sign"><div class="who">{{ $leave->recommender?->name ?? $e->supervisor?->display_name }}</div><span class="n">(Authorized Officer)</span></div></td>
    </tr>
    <tr>
        <td colspan="2"><div class="lbl">7.C Approved for</div>
            <div class="opt"><span class="line" style="min-width:15mm">{{ $leave->status === 'approved' ? rtrim(rtrim(number_format($leave->days_with_pay, 3), '0'), '.') : '' }}</span> days with pay</div>
            <div class="opt"><span class="line" style="min-width:15mm">{{ $leave->status === 'approved' && $leave->days_without_pay ? rtrim(rtrim(number_format($leave->days_without_pay, 3), '0'), '.') : '' }}</span> days without pay</div>
            <div class="opt"><span class="line" style="min-width:15mm"></span> others (specify)</div></td>
        <td><div class="lbl">7.D Disapproved due to</div>
            <div class="line" style="width:100%;min-height:5mm">{{ $leave->status === 'disapproved' && $leave->approved_by ? $leave->remarks : '' }}</div></td>
    </tr>
    <tr><td colspan="3"><div class="sign" style="margin-top:6mm"><div class="who">{{ $leave->status === 'approved' ? $leave->approver?->name : '' }}</div><span class="n">(Authorized Official)</span></div></td></tr>
</table>
<x-verify-stamp type="form6" :key="'form6:'.$leave->id" :employee="$e" :title="'Application for Leave — '.$e->full_name" :summary="[
    'Employee' => $e->full_name, 'Type of leave' => $leave->type_name,
    'Inclusive dates' => $leave->date_from->format('M j, Y').($leave->date_to->ne($leave->date_from) ? ' – '.$leave->date_to->format('M j, Y') : ''),
    'Working days' => $leave->days + 0, 'Status' => ucfirst($leave->status),
    'Days with pay' => $leave->status === 'approved' ? $leave->days_with_pay + 0 : null,
    'Days without pay' => $leave->status === 'approved' && $leave->days_without_pay ? $leave->days_without_pay + 0 : null,
]" />
<p class="small" style="margin-top:3mm">Reference no. LV-{{ str_pad($leave->id, 6, '0', STR_PAD_LEFT) }} · Status: {{ ucfirst($leave->status) }}{{ $leave->approved_at ? ' on '.$leave->approved_at->format('M j, Y') : '' }}</p>
@endsection
