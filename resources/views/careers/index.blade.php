@extends('layouts.app')
@section('title', 'Careers')
@section('bare', true)
@section('content')
@include('careers._head')
<h1>Open positions</h1>
<p class="muted">The {{ \App\Models\Setting::get('agency_name') }} is an equal opportunity employer. Selection is based on merit and fitness, without discrimination on account of gender, civil status, disability, religion, ethnicity or political affiliation.</p>
@forelse ($vacancies as $v)
    <a class="card card-link" href="{{ route('careers.show', $v) }}" style="display:block;margin-bottom:12px;text-decoration:none;color:inherit">
        <h2 style="margin:0 0 4px">{{ $v->title }}</h2>
        <div class="small muted">{{ $v->type_label }}{{ $v->salary_grade ? ' · SG '.$v->salary_grade : '' }}{{ $v->monthly_salary ? ' · ₱'.number_format($v->monthly_salary, 2).' a month' : '' }}{{ $v->place_of_assignment ? ' · '.$v->place_of_assignment : '' }}{{ $v->slots > 1 ? ' · '.$v->slots.' slots' : '' }}</div>
        @if ($v->closing_on)<div class="small" style="margin-top:6px">Apply until <b>{{ $v->closing_on->format('F j, Y') }}</b></div>@endif
    </a>
@empty
    <section class="card"><p class="empty">No open positions right now. Please check back soon.</p></section>
@endforelse
@endsection
