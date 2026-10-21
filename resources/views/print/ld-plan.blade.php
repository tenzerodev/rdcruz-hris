@extends('print._layout')
@section('title', 'Learning & Development Plan '.$year)
@section('page-size', 'A4 landscape')
@section('content')
<style>.page { width: 297mm; min-height: 210mm; }</style>
<x-print-header />
<h1>LEARNING AND DEVELOPMENT PLAN {{ $year }}</h1>
<table class="f small">
    <thead><tr><th>No.</th><th>Training / program</th><th>Competency addressed</th><th>Type</th><th>Mode</th><th>Provider</th><th>Schedule</th><th>Hours</th><th>Participants</th><th>Budget (₱)</th></tr></thead>
    <tbody>
    @forelse ($programs as $i => $p)
        <tr><td class="c">{{ $i + 1 }}</td><td>{{ $p->title }}</td><td>{{ $p->competency }}</td><td>{{ \App\Models\Training::TYPES[$p->type] ?? '' }}</td><td>{{ \App\Models\TrainingProgram::MODES[$p->mode] }}</td>
            <td>{{ $p->provider }}</td><td>{{ $p->when_label }}</td><td class="c">{{ $p->hours }}</td><td class="c">{{ $p->participants_count ?: $p->slots }}</td><td class="r">{{ $p->budget ? number_format($p->budget, 2) : '' }}</td></tr>
    @empty <tr><td colspan="10" class="c">No trainings planned.</td></tr> @endforelse
    <tr><th colspan="9" class="r">TOTAL</th><th class="r">{{ number_format($programs->sum('budget'), 2) }}</th></tr>
    </tbody>
</table>
<table style="width:100%;margin-top:14mm"><tr>
    <td class="sign"><div class="who">{{ $settings['hr_head_name'] ?? '' }}</div><span class="n">Prepared by · {{ $settings['hr_head_title'] ?? 'HRMO' }}</span></td>
    <td class="sign"><div class="who">{{ $settings['agency_head_name'] ?? '' }}</div><span class="n">Approved by · {{ $settings['agency_head_title'] ?? 'Head of Agency' }}</span></td>
</tr></table>
@endsection
