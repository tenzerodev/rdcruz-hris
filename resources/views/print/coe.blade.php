@extends('print._layout')
@section('title', 'Certificate of Employment — '.$employee->full_name)
@section('content')
@php $rules = $employee->rules(); @endphp
<x-print-header style="margin-top:8mm" />
<h1 style="margin:14mm 0 10mm">CERTIFICATE OF {{ $rules['group'] === 'plantilla' ? 'EMPLOYMENT' : 'ENGAGEMENT' }}</h1>
<p style="font-size:11.5pt;line-height:1.8;text-indent:12mm;text-align:justify">
    This is to certify that <b>{{ mb_strtoupper($employee->display_name) }}</b>
    @if ($rules['group'] === 'plantilla')
        has been employed with the {{ $settings['agency_name'] }} as <b>{{ $employee->position }}</b>{{ $employee->salary_grade ? ' (SG '.$employee->salary_grade.')' : '' }}, {{ strtolower($employee->type_label) }} status,
        {{ $employee->office ? 'assigned at the '.$employee->office.',' : '' }}
        from {{ $employee->date_hired?->format('F j, Y') ?? '—' }} {{ $employee->date_separated ? 'to '.$employee->date_separated->format('F j, Y') : 'up to the present' }}.
    @elseif ($rules['group'] === 'dbp' || $rules['group'] === 'service_provider')
        {{ $employee->date_separated ? 'was' : 'is' }} assigned to the {{ $settings['agency_name'] }} as <b>{{ $employee->position }}</b>, {{ $employee->type_label }}{{ $employee->agency_name ? ' from '.$employee->agency_name : '' }},
        from {{ ($employee->contract_start ?? $employee->date_hired)?->format('F j, Y') ?? '—' }} {{ $employee->contract_end ? 'to '.$employee->contract_end->format('F j, Y') : 'up to the present' }}.
    @else
        {{ $employee->date_separated ? 'was' : 'is' }} engaged by the {{ $settings['agency_name'] }} under a <b>{{ $employee->type_label }}</b> as <b>{{ $employee->position }}</b>,
        from {{ $employee->contract_start?->format('F j, Y') ?? '—' }} to {{ $employee->contract_end?->format('F j, Y') ?? 'the present' }}.
        Services rendered under a contract of service or job order are not considered government service.
    @endif
</p>
<p style="font-size:11.5pt;line-height:1.8;text-indent:12mm">This certification is issued upon the request of the above-named{{ $purpose ? ' for '.$purpose : ' for whatever legal purpose it may serve' }}.</p>
<p style="font-size:11.5pt;line-height:1.8;text-indent:12mm">Issued this {{ now()->format('jS') }} day of {{ now()->format('F Y') }}.</p>
<div style="margin-top:22mm;margin-left:55%" class="sign"><div class="who">&nbsp;</div><span class="n">Human Resource Officer</span></div>
<x-verify-stamp type="coe" :key="'coe:'.$employee->id.':'.md5((string) $purpose)" :employee="$employee" :title="'Certificate of '.($rules['group'] === 'plantilla' ? 'Employment' : 'Engagement').' — '.$employee->full_name" :summary="[
    'Name' => $employee->full_name, 'Position' => $employee->position, 'Employment' => $employee->type_label,
    'Period' => (($rules['contract'] ? $employee->contract_start : $employee->date_hired)?->format('M j, Y') ?? '—').' to '.($employee->date_separated?->format('M j, Y') ?? ($rules['contract'] ? $employee->contract_end?->format('M j, Y') : null) ?? 'present'),
    'Purpose' => $purpose, 'Date issued' => now()->format('M j, Y'),
]" style="margin-top:30mm" />
@endsection
