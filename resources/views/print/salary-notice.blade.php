@extends('print._layout')
@section('title', $notices->count() === 1 ? $notices->first()->type_name.' — '.$notices->first()->employee->full_name : 'Salary notices ('.$notices->count().')')
@section('content')
@php $money = fn ($v) => number_format((float) $v, 2); @endphp
@foreach ($notices as $n)
    @php $e = $n->employee; @endphp
    <div style="{{ $loop->last ? '' : 'page-break-after:always;break-after:page;' }}">
        <x-print-header />
        <h1>{{ mb_strtoupper($n->type_name) }}</h1>
        <p style="text-align:right">{{ $n->created_at->format('F j, Y') }}</p>
        <p><b>{{ mb_strtoupper($e->display_name) }}</b><br>{{ $e->position }}<br>{{ $e->office }}<br>{{ $settings['agency_name'] }}</p>
        <p>Sir/Madam:</p>
        @if ($n->type === 'nosi')
            <p style="text-indent:12mm;text-align:justify;line-height:1.7">Pursuant to Joint Civil Service Commission and Department of Budget and Management Circular No. 1, s. 2012, implementing Section 13 (c) of the Salary Standardization Law, your salary is hereby adjusted effective <b>{{ $n->effective_date->format('F j, Y') }}</b>, in recognition of your three (3) years of continuous satisfactory service, as follows:</p>
        @else
            <p style="text-indent:12mm;text-align:justify;line-height:1.7">Pursuant to {{ $n->basis ?: 'the salary schedule in effect' }}, your salary is hereby adjusted effective <b>{{ $n->effective_date->format('F j, Y') }}</b>, as follows:</p>
        @endif
        <table class="f" style="margin:4mm 0 4mm 12mm;width:auto">
            <tr><td style="padding-right:10mm">1. Adjusted monthly basic salary effective {{ $n->effective_date->format('F j, Y') }} (SG {{ $n->new_grade }}, Step {{ $n->new_step }})</td><td style="text-align:right">₱ {{ $money($n->new_salary) }}</td></tr>
            <tr><td>2. Actual monthly basic salary before the adjustment (SG {{ $n->old_grade }}, Step {{ $n->old_step ?: 1 }})</td><td style="text-align:right">₱ {{ $money($n->old_salary) }}</td></tr>
            <tr><td><b>3. Monthly salary adjustment (1 − 2)</b></td><td style="text-align:right"><b>₱ {{ $money($n->new_salary - $n->old_salary) }}</b></td></tr>
        </table>
        <p style="text-indent:12mm;text-align:justify;line-height:1.7">This adjustment is subject to review and post-audit, and to appropriate re-adjustment and refund if found not in order.</p>
        <div style="margin-top:16mm;margin-left:55%" class="sign"><div class="who">{{ $settings['agency_head_name'] ?? '' }}</div><span class="n">{{ $settings['agency_head_title'] ?? 'Head of Agency' }}</span></div>
        <p class="small" style="margin-top:10mm">Position title: {{ $e->position }}{{ $e->plantillaPosition ? ' · Item No. '.$e->plantillaPosition->item_no : '' }}<br>
            Copy furnished: GSIS · Accounting · 201 File</p>
        <x-verify-stamp :type="$n->type" :key="'notice:'.$n->id" :employee="$e" :title="$n->type_name.' — '.$e->full_name" :summary="[
            'Employee' => $e->full_name, 'Effective' => $n->effective_date->format('M j, Y'), 'Salary grade / step' => 'SG '.$n->new_grade.'-'.$n->new_step,
            'New monthly salary' => '₱'.$money($n->new_salary),
        ]" style="margin-top:8mm" />
    </div>
@endforeach
@endsection
