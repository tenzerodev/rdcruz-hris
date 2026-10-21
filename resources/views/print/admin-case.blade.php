@extends('print._layout')
@section('title', 'Case record '.$case->case_no)
@section('content')
<x-print-header />
<h1>ADMINISTRATIVE CASE RECORD</h1>
<p class="c small" style="margin-top:-2mm">CONFIDENTIAL</p>
<table class="f">
    <tr><td style="width:25%"><div class="lbl">Case no.</div><div class="val">{{ $case->case_no }}</div></td><td><div class="lbl">Respondent</div><div class="val">{{ $case->employee->full_name }}</div><div class="small">{{ $case->employee->position }}, {{ $case->employee->office }}</div></td></tr>
    <tr><td><div class="lbl">Date filed</div><div class="val">{{ $case->date_filed->format('F j, Y') }}</div></td><td><div class="lbl">Complainant</div><div class="val">{{ $case->complainant ?: '—' }}</div></td></tr>
    <tr><td><div class="lbl">Status</div><div class="val">{{ $case->status_label }}</div></td><td><div class="lbl">Offense charged</div><div class="val">{{ $case->offense }}{{ $case->classification ? ' ('.\App\Models\AdminCase::CLASSIFICATIONS[$case->classification].')' : '' }}</div></td></tr>
    @if ($case->description)<tr><td colspan="2"><div class="lbl">Summary</div>{!! nl2br(e($case->description)) !!}</td></tr>@endif
</table>
<h3 style="margin:5mm 0 2mm">Record of proceedings</h3>
<table class="f small">
    <thead><tr><th style="width:18%">Date</th><th style="width:28%">Step</th><th>Details</th></tr></thead>
    <tbody>@foreach ($case->events as $e)<tr><td>{{ $e->date->format('M j, Y') }}</td><td>{{ $e->type_name }}{{ $e->due_on ? ' (due '.$e->due_on->format('M j').')' : '' }}</td><td>{!! nl2br(e($e->details)) !!}{{ $e->document_name ? ' [Attachment: '.$e->document_name.']' : '' }}</td></tr>@endforeach</tbody>
</table>
@if ($case->suspensions->isNotEmpty())
<h3 style="margin:5mm 0 2mm">Suspensions</h3>
<table class="f small">@foreach ($case->suspensions as $s)<tr><td>{{ \App\Models\CaseSuspension::KINDS[$s->kind] }}</td><td>{{ $s->date_from->format('M j, Y') }} – {{ $s->date_to->format('M j, Y') }}</td><td>{{ $s->with_pay ? 'With pay' : 'Without pay' }}</td></tr>@endforeach</table>
@endif
@if ($case->decided_on)
<h3 style="margin:5mm 0 2mm">Decision — {{ $case->decided_on->format('F j, Y') }}</h3>
<p><b>{{ \App\Models\AdminCase::PENALTIES[$case->penalty] ?? '' }}.</b> {!! nl2br(e($case->decision)) !!}</p>
@endif
<p class="small" style="margin-top:8mm">Printed {{ now()->format('F j, Y g:i A') }} by {{ auth()->user()->name }}.</p>
@endsection
