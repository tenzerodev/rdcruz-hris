@extends('layouts.app')
@section('title', 'IPCR')
@section('content')
<div class="page-head"><div><h1>Performance (IPCR)</h1><div class="muted">Individual Performance Commitment and Review under the Strategic Performance Management System.</div></div></div>
@if ($current && ! $mine->firstWhere('performance_period_id', $current->id))
    <div class="card" style="margin-bottom:16px">
        <h2>{{ $current->name }}</h2>
        <p class="small muted">{{ $current->starts_on->format('M j') }} – {{ $current->ends_on->format('M j, Y') }} · {{ \App\Models\PerformancePeriod::STATUSES[$current->status] }}</p>
        <form method="post" action="{{ route('me.ipcr.start', $current) }}">@csrf<button class="btn btn-primary">Start my IPCR</button></form>
    </div>
@endif
<div class="grid grid-2">
    <section class="card">
        <h2>My IPCRs</h2>
        <table class="table"><tbody>
        @forelse ($mine as $i)
            <tr><td><a href="{{ route('me.ipcr.show', $i) }}">{{ $i->period->name }}</a><div class="small muted">{{ $i->status_label }}</div></td>
                <td class="num">{{ $i->final_rating ? number_format($i->final_rating, 2).' · '.$i->adjectival : '' }}</td></tr>
        @empty <tr><td class="muted">None yet. HR opens a rating period each semester.</td></tr> @endforelse
        </tbody></table>
    </section>
    @if ($team->isNotEmpty())
    <section class="card">
        <h2>My team</h2>
        <table class="table"><tbody>
        @foreach ($team as $i)
            <tr><td><a href="{{ route('me.ipcr.show', $i) }}">{{ $i->employee->full_name }}</a><div class="small muted">{{ $i->period->name }}</div></td>
                <td>@php $act = in_array($i->status, ['targets_submitted', 'self_rated']); @endphp<span class="badge {{ $act ? 'badge-warn' : '' }}">{{ $i->status_label }}</span></td></tr>
        @endforeach
        </tbody></table>
    </section>
    @endif
    @if ($offices->isNotEmpty())
    <section class="card">
        <h2>Office / division ratings I head</h2>
        <table class="table"><tbody>
        @foreach ($offices as $o)
            <tr><td><a href="{{ route('performance.show', $o) }}">{{ $o->level_name }} — {{ $o->office }}</a><div class="small muted">{{ $o->period->name }}</div></td>
                <td><span class="badge {{ $o->status === 'draft' ? 'badge-warn' : '' }}">{{ $o->status_label }}</span></td></tr>
        @endforeach
        </tbody></table>
    </section>
    @endif
</div>
@endsection
