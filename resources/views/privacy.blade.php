@extends('layouts.app')
@section('title', 'Privacy notice')
@section('bare', true)
@section('main-class', 'setup')
@section('content')
@php $agency = $s['agency_name']; @endphp
<article class="card">
    <h1>Privacy notice</h1>
    <p class="muted">{{ $agency }} · Human Resource Information System</p>

    <h2>What we collect</h2>
    <ul>
        <li><b>Personal data sheet and 201 file:</b> name, birth date, contact details, family, education, eligibility, work history, trainings and uploaded documents.</li>
        <li><b>Government ID numbers</b> (TIN, GSIS, PhilHealth, Pag-IBIG, SSS). These are stored encrypted.</li>
        <li><b>Attendance:</b> time-in and time-out, the kiosk or device used, and, for personal-device punching, your location at that moment if the office requires it.</li>
        <li><b>Face recognition:</b> a face template — 128 numbers calculated from your face. The photo used to make it is not kept. A small photo may be saved with each punch so HR can resolve disputes{{ ! empty($s['snapshot_retention_days']) ? '; it is deleted after '.$s['snapshot_retention_days'].' days' : '' }}.</li>
        <li><b>Leave, overtime, travel, performance (IPCR) and payroll inputs.</b></li>
        <li><b>Sign-in records:</b> username, last sign-in time and IP address, and an audit log of changes to records.</li>
    </ul>

    <h2>Why</h2>
    <p>To keep the records required of a government agency by the Civil Service Commission, COA and the GSIS — daily time records, leave cards, service records and the 201 file — and to process pay, leave and benefits. Processing is based on the agency's legal obligations and functions (Sections 12 and 13 of the Data Privacy Act of 2012, RA 10173). Face recognition is used only with your written consent.</p>

    <h2>Who can see it</h2>
    <p>You see your own records. Your supervisor sees what is needed to approve your requests. HR staff see records according to their role (for example, payroll staff cannot change attendance). Records are shared outside the agency only when the law requires it, for example with CSC, GSIS, COA or BIR.</p>

    <h2>How long we keep it</h2>
    <p>For as long as the government records retention rules require (National Archives of the Philippines). Face data is deleted {{ (int) $s['face_purge_days'] === 0 ? 'as soon as' : $s['face_purge_days'].' days after' }} you leave the agency, or right away if you withdraw consent.</p>

    <h2>Your rights</h2>
    <p>You can be informed, access your data, have it corrected, object, have face data erased, and download a copy (sign in and open <b>My data &amp; privacy</b>). You may withdraw consent for face recognition at any time; you will then record attendance another way. You can also file a complaint with the National Privacy Commission.</p>

    @if (! empty($s['privacy_extra']))
        <h2>More from {{ $agency }}</h2>
        <div style="white-space:pre-line">{{ $s['privacy_extra'] }}</div>
    @endif

    <h2>Contact our Data Protection Officer</h2>
    @if ($s['dpo_name'] || $s['dpo_email'])
        <p>{{ $s['dpo_name'] }}@if ($s['dpo_email']) · <a href="mailto:{{ $s['dpo_email'] }}">{{ $s['dpo_email'] }}</a>@endif @if ($s['dpo_phone']) · {{ $s['dpo_phone'] }}@endif</p>
    @else
        <p>Please contact the HR office.</p>
    @endif
    <p class="small muted"><a href="{{ url('/') }}">Back to the HRIS</a></p>
</article>
@endsection
