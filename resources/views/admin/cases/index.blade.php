@extends('layouts.app')
@section('title', 'Administrative cases')
@section('content')
<div class="page-head"><div><h1>Administrative cases</h1><div class="muted">Confidential. Only HR officers and system administrators can see this page; every view is logged.</div></div></div>
@if ($due->isNotEmpty())
    <div class="alert alert-warn"><x-icon name="alert" /><div><b>Due within 7 days:</b>
        @foreach ($due as $e)<div><a href="{{ route('admin.cases.show', $e->adminCase) }}">{{ $e->adminCase->case_no }}</a> — {{ $e->type_name }} ({{ $e->adminCase->employee->full_name }}), due {{ $e->due_on->format('M j') }}{{ $e->due_on->isPast() ? ' (overdue)' : '' }}</div>@endforeach</div></div>
@endif
<nav class="tabs">
    @foreach (['open' => 'Open', 'closed' => 'Decided / closed', 'all' => 'All'] as $k => $l)
        <a href="{{ route('admin.cases.index', ['filter' => $k]) }}" @if ($filter === $k) aria-current="page" @endif>{{ $l }}</a>
    @endforeach
</nav>
<div class="grid" style="grid-template-columns: minmax(0, 2fr) minmax(280px, 1fr)">
    <section class="card">
        <form method="get" class="row" style="align-items:flex-end"><input type="hidden" name="filter" value="{{ $filter }}">
            <div class="field"><label for="q">Search</label><input type="search" id="q" name="q" value="{{ request('q') }}" placeholder="Case no., name, offense"></div><div class="field"><button class="btn">Search</button></div></form>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Case</th><th>Respondent</th><th>Offense</th><th>Status</th></tr></thead>
            <tbody>
            @forelse ($cases as $c)
                <tr><td><a href="{{ route('admin.cases.show', $c) }}"><b>{{ $c->case_no }}</b></a><div class="small muted">Filed {{ $c->date_filed->format('M j, Y') }}</div></td>
                    <td>{{ $c->employee->full_name }}<div class="small muted">{{ $c->employee->office }}</div></td>
                    <td>{{ $c->offense }}@if ($c->classification)<div class="small muted">{{ \App\Models\AdminCase::CLASSIFICATIONS[$c->classification] }}</div>@endif</td>
                    <td><span @class(['badge', 'badge-warn' => $c->isOpen(), 'badge-ok' => ! $c->isOpen()])>{{ $c->status_label }}</span>@if ($c->penalty)<div class="small muted">{{ \App\Models\AdminCase::PENALTIES[$c->penalty] }}</div>@endif</td></tr>
            @empty <tr><td colspan="4" class="empty">No cases here.</td></tr> @endforelse
            </tbody>
        </table></div>
        <div class="pagination">{{ $cases->links() }}</div>
    </section>
    @unless ($readOnly)
    <section class="card">
        <h2>Open a case</h2>
        <form method="post" action="{{ route('admin.cases.store') }}">@csrf
            <div class="field"><label for="employee_id">Respondent</label><select id="employee_id" name="employee_id" required><option value=""></option>@foreach ($employees as $e)<option value="{{ $e->id }}">{{ $e->full_name }}</option>@endforeach</select></div>
            <div class="field"><label for="case_no">Case no.</label><input type="text" id="case_no" name="case_no" placeholder="Blank = next number (AC-{{ now()->year }}-001)"></div>
            <div class="field"><label for="complainant">Complainant</label><input type="text" id="complainant" name="complainant"></div>
            <div class="field"><label for="offense">Offense charged</label><input type="text" id="offense" name="offense" required list="offenses">@include('admin.cases._offenses')</div>
            <div class="field"><label for="classification">Classification</label><select id="classification" name="classification"><option value="">Not yet determined</option>@foreach (\App\Models\AdminCase::CLASSIFICATIONS as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select><div class="hint">See Rule 10 of the 2017 RACCS.</div></div>
            <div class="field"><label for="description">Summary of the complaint</label><textarea id="description" name="description" rows="3"></textarea></div>
            <div class="field"><label for="date_filed">Date filed</label><input type="date" id="date_filed" name="date_filed" value="{{ today()->toDateString() }}" required></div>
            <button class="btn btn-primary">Open case</button>
        </form>
    </section>
    @endunless
</div>
@endsection
