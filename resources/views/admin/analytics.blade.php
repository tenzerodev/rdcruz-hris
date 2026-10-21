@extends('layouts.app')
@section('title', 'HR analytics '.$year)
@section('content')
<div class="page-head">
    <div><h1>HR analytics</h1><div class="muted">Workforce at a glance for {{ $year }}. Headcount and demographics are as of today.</div></div>
    <div class="actions">
        <form method="get"><select name="year" onchange="this.form.submit()" aria-label="Year">@foreach (range(now()->year, now()->year - 5) as $y)<option @selected($y === $year)>{{ $y }}</option>@endforeach</select></form>
        <button type="button" class="btn" onclick="window.print()">Print</button>
    </div>
</div>
<div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr));margin-bottom:16px">
    <div class="card stat"><div class="num">{{ $a['headcount'] }}</div><div class="label">Active personnel</div></div>
    <div class="card stat"><div class="num">{{ $a['plantilla']['rate'] }}%</div><div class="label">Vacancy rate ({{ $a['plantilla']['vacant'] }} of {{ $a['plantilla']['items'] }} items)</div></div>
    <div class="card stat"><div class="num">{{ $a['turnover'] }}%</div><div class="label">Turnover ({{ $a['separated'] }} left, {{ $a['hired'] }} hired)</div></div>
    <div class="card stat"><div class="num">{{ $a['leave_total'] }}</div><div class="label">Days of leave taken</div></div>
    <div class="card stat"><div class="num">{{ $a['training_hours'] }}</div><div class="label">Training hours per person</div></div>
</div>
<div class="grid grid-2">
    <section class="card"><h2>By employment group</h2><x-chart.bars :data="$a['by_group']" /></section>
    <section class="card"><h2>By office</h2><x-chart.bars :data="$a['by_office']" /></section>
    <section class="card"><h2>Hired and separated</h2><x-chart.columns :labels="$a['movement']['labels']" :series="$a['movement']['series']" :colors="['var(--ok)', 'var(--error)']" />
        @if ($a['separation_reasons'])<h3 class="small muted" style="margin:12px 0 6px">Reasons for leaving</h3><x-chart.bars :data="$a['separation_reasons']" color="var(--error)" />@endif</section>
    <section class="card"><h2>Late arrivals and absences</h2><x-chart.columns :labels="$a['tardiness']['labels']" :series="$a['tardiness']['series']" :colors="['var(--warn)', 'var(--error)']" />
        <p class="hint">Times employees arrived late, and days absent without leave, from the DTRs. See <a href="{{ route('admin.reports.habitual', ['year' => $year]) }}">Tardiness &amp; absences</a> for who.</p></section>
    <section class="card"><h2>Leave taken by month</h2><x-chart.columns :labels="$a['leave']['labels']" :series="$a['leave']['series']" />
        <h3 class="small muted" style="margin:12px 0 6px">By type (days)</h3><x-chart.bars :data="$a['leave_by_type']" /></section>
    <section class="card"><h2>Performance ratings</h2>
        @if ($a['ratings']['period'])<p class="small muted">{{ $a['ratings']['period'] }} (final IPCRs)</p><x-chart.bars :data="$a['ratings']['counts']" color="var(--ok)" />
        @else<p class="muted">No rated IPCRs yet.</p>@endif</section>
    <section class="card"><h2>Sex</h2><x-chart.bars :data="$a['sex']" /><h3 class="small muted" style="margin:12px 0 6px">Age</h3><x-chart.bars :data="$a['ages']" /></section>
    <section class="card"><h2>Reaching 65 by {{ $year + 5 }} ({{ $a['retiring']->count() }})</h2>
        <p class="hint">Compulsory retirement age is 65. Plan succession and recruitment early.</p>
        <div class="table-wrap"><table class="table"><tbody>
        @forelse ($a['retiring']->take(15) as $e)
            <tr><td>{{ $e->full_name }}<div class="small muted">{{ $e->position }}{{ $e->office ? ' · '.$e->office : '' }}</div></td><td class="small">turns 65 on {{ $e->birth_date->copy()->addYears(65)->format('M j, Y') }}</td></tr>
        @empty <tr><td class="muted">None.</td></tr> @endforelse
        </tbody></table></div>
    </section>
</div>
@endsection
