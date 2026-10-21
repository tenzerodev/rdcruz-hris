@extends('layouts.app')
@section('title', 'Tardiness & absences')
@section('content')
@php
    $months = range(1, 12);
    $name = fn ($m) => date('M', mktime(0, 0, 0, $m, 1));
@endphp
<div class="page-head">
    <div><h1>Habitual tardiness &amp; absenteeism</h1>
        <div class="muted">From the DTR, by month. Tardy = arrived late, regardless of minutes. Absent = unauthorized absence (no leave, travel or OB).</div></div>
    <a class="btn" href="{{ request()->fullUrlWithQuery(['format' => 'csv']) }}">Download CSV</a>
</div>

<section class="card" style="margin-bottom:16px">
    <form class="inline-form" method="get">
        <div class="field"><label for="year">Year</label><input type="number" id="year" name="year" min="2000" max="2100" value="{{ $year }}" style="width:100px"></div>
        <div class="field"><label for="office">Office</label><select id="office" name="office"><option value="">All offices</option>@foreach ($offices as $o)<option @selected(request('office') === $o)>{{ $o }}</option>@endforeach</select></div>
        <div class="field"><label for="show">Show</label><select id="show" name="show"><option value="flagged" @selected($show === 'flagged')>Only employees who meet a rule</option><option value="all" @selected($show === 'all')>Everyone</option></select></div>
        <button class="btn btn-primary">Show</button>
    </form>
    <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr));margin-top:14px">
        <div class="stat"><div class="num">{{ $tardyCount }}</div><div class="label">Habitually tardy</div></div>
        <div class="stat"><div class="num">{{ $absentCount }}</div><div class="label">Habitual absenteeism</div></div>
        <div class="stat"><div class="num">{{ $total }}</div><div class="label">Employees checked</div></div>
    </div>
    <p class="hint">CSC rules: <b>habitually tardy</b> — tardy {{ $rules['tardy_per_month'] }} or more times a month for at least {{ $rules['tardy_months'] }} months in a semester, or {{ $rules['tardy_months'] }} consecutive months.
        <b>Habitual absenteeism</b> — unauthorized absences over {{ $rules['absent_per_month'] }} days a month for at least {{ $rules['absent_months'] }} months in a semester, or {{ $rules['absent_months'] }} consecutive months.
        Check each case against the DTR and the employee's explanation before any action.</p>
</section>

<section class="card">
    <div class="table-wrap"><table class="table habitual">
        <thead>
            <tr><th rowspan="2">Employee</th><th rowspan="2"></th>@foreach ($months as $m)<th class="m">{{ $name($m) }}</th>@endforeach<th rowspan="2">Finding</th></tr>
        </thead>
        <tbody>
        @forelse ($rows as $r)
            @php $e = $r['employee']; @endphp
            <tr>
                <td rowspan="2"><a href="{{ route('admin.employees.show', $e) }}"><b>{{ $e->full_name }}</b></a><div class="small muted">{{ $e->office }}</div></td>
                <td class="small muted">Tardy</td>
                @foreach ($months as $m)
                    @php $v = $r['months'][$m]['tardy'] ?? null; @endphp
                    <td @class(['m', 'hit' => $v !== null && $v >= $rules['tardy_per_month']])>{{ $v ?? '' }}</td>
                @endforeach
                <td class="small">@if ($r['tardy'])<span class="badge badge-error">Habitually tardy</span><div>{{ $r['tardy'] }}</div>@endif</td>
            </tr>
            <tr>
                <td class="small muted">Absent</td>
                @foreach ($months as $m)
                    @php $v = $r['months'][$m]['absent'] ?? null; @endphp
                    <td @class(['m', 'hit' => $v !== null && $v > $rules['absent_per_month']])>{{ $v === null ? '' : rtrim(rtrim(number_format($v, 1), '0'), '.') }}</td>
                @endforeach
                <td class="small">@if ($r['absent'])<span class="badge badge-error">Habitual absenteeism</span><div>{{ $r['absent'] }}</div>@endif</td>
            </tr>
        @empty
            <tr><td colspan="15" class="empty">{{ $show === 'flagged' ? 'No one meets either rule for '.$year.'.' : 'No employees.' }}</td></tr>
        @endforelse
        </tbody>
    </table></div>
</section>
@endsection
