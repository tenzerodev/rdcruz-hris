@extends('layouts.app')
@section('title', 'Training & development')
@section('content')
@php $money = fn ($v) => '₱'.number_format((float) $v, 2); @endphp
<div class="page-head">
    <div><h1>Training &amp; development {{ $year }}</h1><div class="muted">Employees' learning needs, the L&amp;D plan, nominations and training hours. Completed trainings are added to each attendee's 201 file.</div></div>
    <div class="actions">
        <form method="get"><input type="hidden" name="tab" value="{{ $tab }}"><select name="year" onchange="this.form.submit()" aria-label="Year">@foreach (range(now()->year + 1, now()->year - 4) as $y)<option @selected($y === $year)>{{ $y }}</option>@endforeach</select></form>
        <a class="btn" href="{{ route('admin.learning.print', ['year' => $year]) }}" target="_blank">Print L&amp;D plan</a>
    </div>
</div>
<nav class="tabs">
    @foreach (['plan' => 'L&D plan', 'needs' => 'Learning needs', 'hours' => 'Training hours'] as $k => $l)
        <a href="{{ route('admin.learning.index', ['year' => $year, 'tab' => $k]) }}" @if ($tab === $k) aria-current="page" @endif>{{ $l }}</a>
    @endforeach
</nav>

@if ($tab === 'plan')
<div class="grid" style="grid-template-columns: minmax(0, 2fr) minmax(280px, 1fr)">
    <section class="card">
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Training</th><th>When</th><th class="num">Participants</th><th class="num">Budget</th><th>Status</th></tr></thead>
            <tbody>
            @forelse ($programs as $p)
                <tr>
                    <td><a href="{{ route('admin.learning.show', $p) }}"><b>{{ $p->title }}</b></a><div class="small muted">{{ $p->competency }}{{ $p->provider ? ' · '.$p->provider : '' }} · {{ \App\Models\TrainingProgram::MODES[$p->mode] }}</div></td>
                    <td class="small">{{ $p->when_label }}{{ $p->hours ? ' · '.$p->hours.' h' : '' }}</td>
                    <td class="num">{{ $p->status === 'done' ? $p->attended_count.' attended' : $p->participants_count.($p->slots ? ' / '.$p->slots : '') }}</td>
                    <td class="num">{{ $p->budget ? $money($p->budget) : '—' }}@if ($p->actual_cost)<div class="small muted">spent {{ $money($p->actual_cost) }}</div>@endif</td>
                    <td><span @class(['badge', 'badge-ok' => $p->status === 'done', 'badge-info' => $p->status === 'scheduled'])>{{ \App\Models\TrainingProgram::STATUSES[$p->status] }}</span></td>
                </tr>
            @empty <tr><td colspan="5" class="empty">No trainings planned for {{ $year }} yet.</td></tr> @endforelse
            </tbody>
            @if ($programs->isNotEmpty())
            <tfoot><tr><th colspan="3">Total ({{ $programs->where('status', '!=', 'cancelled')->count() }} trainings)</th><th class="num">{{ $money($programs->where('status', '!=', 'cancelled')->sum('budget')) }}<div class="small muted">spent {{ $money($programs->sum('actual_cost')) }}</div></th><th></th></tr></tfoot>
            @endif
        </table></div>
    </section>
    @unless ($readOnly)
    <section class="card">
        <h2>Add to the plan</h2>
        <form method="post" action="{{ route('admin.learning.store') }}">@csrf
            @include('admin.learning._fields', ['year' => $year])
            <button class="btn btn-primary">Add</button>
        </form>
    </section>
    @endunless
</div>
@elseif ($tab === 'needs')
@php $grouped = $needs->groupBy(fn ($n) => mb_strtolower(trim($n->training_title ?: $n->competency)))->sortByDesc(fn ($g) => $g->count()); @endphp
<section class="card">
    <p class="muted">{{ $needs->count() }} need(s) from {{ $needs->pluck('employee_id')->unique()->count() }} employee(s), grouped by training requested. Add a group to the plan to nominate everyone in it.</p>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Training / competency</th><th class="num">Employees</th><th>Priority</th><th>Who</th><th></th></tr></thead>
        <tbody>
        @forelse ($grouped as $group)
            @php $first = $group->first(); $open = $group->whereNull('training_program_id'); @endphp
            <tr>
                <td><b>{{ $first->training_title ?: $first->competency }}</b><div class="small muted">{{ $group->pluck('competency')->unique()->join('; ') }}</div></td>
                <td class="num">{{ $group->count() }}</td>
                <td class="small">{{ $group->countBy('priority')->map(fn ($n, $p) => \App\Models\TrainingNeed::PRIORITIES[$p].' '.$n)->join(', ') }}</td>
                <td class="small">{{ $group->map(fn ($n) => $n->employee->full_name)->join('; ') }}</td>
                <td>@if ($open->isEmpty())<a class="small" href="{{ route('admin.learning.show', $group->firstWhere('training_program_id')->training_program_id) }}">In the plan</a>
                    @elseif (! $readOnly)<details class="inline-form"><summary class="btn btn-sm">Add to plan</summary>
                        <form method="post" action="{{ route('admin.learning.store') }}" class="card" style="margin-top:8px;min-width:280px">@csrf
                            @foreach ($open as $n)<input type="hidden" name="need_ids[]" value="{{ $n->id }}">@endforeach
                            @include('admin.learning._fields', ['year' => $year, 'prefill' => ['title' => $first->training_title ?: $first->competency, 'competency' => $first->competency]])
                            <button class="btn btn-primary btn-sm">Add</button>
                        </form></details>@endif</td>
            </tr>
        @empty <tr><td colspan="5" class="empty">No learning needs submitted for {{ $year }}. Employees add them under Training on their own pages.</td></tr> @endforelse
        </tbody>
    </table></div>
</section>
@else
<section class="card">
    <p class="muted">Training hours recorded on 201 files for trainings that started in {{ $year }}. Target: {{ $target }} hours a year.</p>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Employee</th><th>Office</th><th class="num">Trainings</th><th class="num">Hours</th><th style="width:30%"></th></tr></thead>
        <tbody>
        @foreach ($hours as $h)
            <tr><td><a href="{{ route('admin.employees.show', [$h['employee'], 'tab' => 'pds']) }}">{{ $h['employee']->full_name }}</a></td><td class="small">{{ $h['employee']->office }}</td>
                <td class="num">{{ $h['count'] }}</td><td class="num"><b>{{ $h['hours'] }}</b></td>
                <td><div class="meter"><span style="width:{{ min(100, round($h['hours'] / max(1, $target) * 100)) }}%;background:{{ $h['hours'] >= $target ? 'var(--ok)' : 'var(--warn)' }}"></span></div></td></tr>
        @endforeach
        </tbody>
    </table></div>
</section>
@endif
@endsection
