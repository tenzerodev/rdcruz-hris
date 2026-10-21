@extends('print._layout')
@section('title', 'Comparative assessment — '.$vacancy->title)
@section('page-size', 'A4 landscape')
@section('content')
@php $criteria = config('hris.recruitment.criteria'); @endphp
<style>.page { width: 297mm; min-height: 210mm; }</style>
<x-print-header />
<h1>COMPARATIVE ASSESSMENT RESULT</h1>
<p class="c" style="margin:0 0 4mm"><b>{{ $vacancy->title }}</b>{{ $vacancy->item_no ? ' · Item No. '.$vacancy->item_no : '' }}{{ $vacancy->salary_grade ? ' · SG '.$vacancy->salary_grade : '' }}{{ $vacancy->place_of_assignment ? ' · '.$vacancy->place_of_assignment : '' }}</p>
<table class="f">
    <thead><tr><th>Rank</th><th>Name of applicant</th>@foreach ($criteria as $c)<th>{{ $c['label'] }}<br><span class="small">({{ $c['max'] }})</span></th>@endforeach<th>TOTAL<br><span class="small">(100)</span></th><th>Remarks</th></tr></thead>
    <tbody>
    @forelse ($rows as $i => $r)
        <tr><td class="c">{{ $r['scores']['total'] !== null ? $i + 1 : '' }}</td><td>{{ $r['applicant']->full_name }}{{ $r['applicant']->employee_id ? ' (internal)' : '' }}</td>
            @foreach ($criteria as $k => $c)<td class="c">{{ $r['scores']['assessors'] ? number_format($r['scores']['criteria'][$k], 2) : '' }}</td>@endforeach
            <td class="c"><b>{{ $r['scores']['total'] !== null ? number_format($r['scores']['total'], 2) : 'not scored' }}</b></td>
            <td class="small">{{ \App\Models\Applicant::STATUSES[$r['applicant']->status] }}</td></tr>
    @empty <tr><td colspan="{{ count($criteria) + 4 }}" class="c">No qualified applicants.</td></tr> @endforelse
    </tbody>
</table>
<p class="small">Scores are the average of the HRMPSB members' individual ratings. Only applicants who meet the qualification standards are included.</p>
<table style="width:100%;margin-top:14mm"><tr>
    <td class="sign"><div class="who">&nbsp;</div><span class="n">HRMPSB Chairperson</span></td>
    <td class="sign"><div class="who">&nbsp;</div><span class="n">HRMPSB Member</span></td>
    <td class="sign"><div class="who">&nbsp;</div><span class="n">HRMPSB Member</span></td>
    <td class="sign"><div class="who">{{ $settings['agency_head_name'] ?? '' }}</div><span class="n">Appointing Authority</span></td>
</tr></table>
@endsection
