@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')
@php
    $hour = now()->hour;
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
    $a = $attendance;
    $pct = fn ($n) => $a['total'] ? round($n / $a['total'] * 100, 1) : 0;
    $initials = fn ($e) => mb_strtoupper(mb_substr($e->first_name, 0, 1).mb_substr($e->last_name, 0, 1));
    $actions = [
        ['Leave to approve', $inbox['leave_ready'], $inbox['leave_waiting'] ? $inbox['leave_waiting'].' more waiting for supervisors' : 'Recommended by supervisors', 'calendar', route('admin.leave.index', ['status' => 'recommended'])],
        ['Overtime requests', $inbox['overtime'], 'Pending approval', 'timer', route('admin.overtime.index')],
        ['Travel / OB requests', $inbox['travel'], 'Pending approval', 'pin', route('admin.travel.index')],
        ['Contracts ending', $expiring->count(), 'Within '.config('hris.contract_warning_days').' days', 'alert', route('admin.reports.workforce')],
    ];
    $totalActions = collect($actions)->sum(fn ($x) => $x[1]);
@endphp

<div class="page-head">
    <div>
        <h1>{{ $greeting }}, {{ explode(' ', auth()->user()->name)[0] }}</h1>
        <div class="muted">{{ now()->format('l, F j, Y') }}@unless ($isWorkday) · <b>No work today</b> (weekend or holiday)@endunless</div>
    </div>
    <div class="actions">
        <a class="btn" href="{{ route('admin.leave.index') }}"><x-icon name="calendar" size="16" /> File leave</a>
        <a class="btn" href="{{ route('admin.travel.index') }}"><x-icon name="pin" size="16" /> Issue travel order</a>
        <a class="btn btn-primary" href="{{ route('admin.employees.create') }}"><x-icon name="plus" size="16" /> Add employee</a>
    </div>
</div>

<div class="section-title" style="margin-top:0">{{ $totalActions ? 'Needs your attention' : 'Needs your attention — all clear' }}</div>
<div class="grid grid-4">
    @foreach ($actions as [$title, $n, $desc, $icon, $url])
        <a href="{{ $url }}" @class(['action-card', 'hot' => $n > 0, 'clear' => ! $n])>
            <span class="ic"><x-icon :name="$icon" size="20" /></span>
            <span><span class="n">{{ $n }}</span><span class="t" style="display:block">{{ $title }}</span><span class="d">{{ $n ? $desc : 'Nothing to do' }}</span></span>
        </a>
    @endforeach
</div>

<div class="section-title">Today's attendance</div>
<section class="card">
    <div class="card-head">
        <h2>{{ $a['present'] }} of {{ $a['total'] }} in today</h2>
        <a class="card-link" href="{{ route('admin.punches.index') }}">Attendance log <x-icon name="arrow" size="14" /></a>
    </div>
    <div class="meter" role="img" aria-label="{{ $a['present'] }} present, {{ $a['away'] }} on leave or travel, {{ $a['not_in'] }} not in yet">
        <span style="width:{{ $pct($a['present']) }}%;background:var(--ok)"></span>
        <span style="width:{{ $pct($a['away']) }}%;background:var(--primary)"></span>
    </div>
    <div class="legend">
        <span><i style="background:var(--ok)"></i>Present <b>{{ $a['present'] }}</b></span>
        <span><i style="background:var(--primary)"></i>On leave / travel <b>{{ $a['away'] }}</b></span>
        <span><i style="background:var(--surface-3)"></i>Not in yet <b>{{ $a['not_in'] }}</b></span>
        <span class="muted">Faces enrolled {{ $enrolled }}/{{ $a['total'] }}</span>
    </div>
</section>

<div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); margin-top:16px; align-items:start">
    <section class="card">
        <div class="card-head"><h2>Latest time-ins</h2></div>
        @if ($latest->isEmpty())
            <div class="empty">No one has timed in yet today.</div>
        @else
            <ul class="people">
                @foreach ($latest as $p)
                    <li>
                        <span class="initials">@if ($p->snapshot_path)<img src="{{ route('admin.punches.snapshot', $p) }}" alt="" loading="lazy">@else{{ $initials($p->employee) }}@endif</span>
                        <span class="who"><b>{{ $p->employee->display_name }}</b><small>{{ $p->label }} · {{ $p->kiosk?->name ?? ucfirst($p->source) }}</small></span>
                        <span class="time">{{ $p->punched_at->format('g:i A') }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <section class="card">
        <div class="card-head"><h2>Out today</h2></div>
        @if ($onLeave->isEmpty() && $onTravel->isEmpty())
            <div class="empty">No one is on leave or travel today.</div>
        @else
            <ul class="people">
                @foreach ($onLeave as $l)
                    <li><span class="initials">{{ $initials($l->employee) }}</span><span class="who"><b>{{ $l->employee->display_name }}</b><small>{{ $l->type_name }}</small></span><span class="badge">{{ $l->type }}</span></li>
                @endforeach
                @foreach ($onTravel as $o)
                    @foreach ($o->employees as $e)
                        <li><span class="initials">{{ $initials($e) }}</span><span class="who"><b>{{ $e->display_name }}</b><small>{{ $o->destination }}</small></span><span class="badge badge-info">{{ \App\Models\TravelOrder::SHORT[$o->type] }}</span></li>
                    @endforeach
                @endforeach
            </ul>
        @endif
    </section>

    <section class="card">
        <div class="card-head"><h2>Not in yet</h2><span class="muted small">{{ $notYet->count() }}</span></div>
        @if ($notYet->isEmpty())
            <div class="empty">{{ $isWorkday ? 'Everyone is accounted for.' : 'No work today.' }}</div>
        @else
            <ul class="people">
                @foreach ($notYet->take(6) as $e)
                    <li><span class="initials">{{ $initials($e) }}</span><span class="who"><b>{{ $e->display_name }}</b><small>{{ $e->office ?: $e->type_label }}</small></span></li>
                @endforeach
            </ul>
            @if ($notYet->count() > 6)
                <details class="more"><summary>Show {{ $notYet->count() - 6 }} more</summary>
                    <ul class="people">@foreach ($notYet->skip(6) as $e)<li><span class="initials">{{ $initials($e) }}</span><span class="who"><b>{{ $e->display_name }}</b><small>{{ $e->office ?: $e->type_label }}</small></span></li>@endforeach</ul>
                </details>
            @endif
        @endif
    </section>
</div>

<div class="grid grid-2" style="margin-top:16px; align-items:start">
    <section class="card">
        <div class="card-head"><h2>Contracts ending soon</h2><a class="card-link" href="{{ route('admin.reports.workforce') }}">Workforce <x-icon name="arrow" size="14" /></a></div>
        @if ($expiring->isEmpty())
            <div class="empty">No COS, JO or service-provider contracts end in the next {{ config('hris.contract_warning_days') }} days.</div>
        @else
            <ul class="people">
                @foreach ($expiring as $e)
                    <li><span class="who"><b><a href="{{ route('admin.employees.show', $e) }}">{{ $e->display_name }}</a></b><small>{{ $e->type_label }}{{ $e->agency_name ? ' · '.$e->agency_name : '' }}</small></span>
                        <span class="badge {{ $e->contract_end->isPast() ? 'badge-error' : 'badge-warn' }}">{{ $e->contract_end->isPast() ? 'Ended' : 'Ends' }} {{ $e->contract_end->format('M j') }}</span></li>
                @endforeach
            </ul>
        @endif
    </section>
    <section class="card">
        <div class="card-head"><h2>Kiosks</h2><a class="card-link" href="{{ route('admin.kiosks.index') }}">Manage <x-icon name="arrow" size="14" /></a></div>
        @if ($kiosks->isEmpty())
            <div class="empty">No kiosks yet. <a href="{{ route('admin.kiosks.index') }}">Set up a time-in device</a>.</div>
        @else
            <ul class="people">
                @foreach ($kiosks as $k)
                    @php $online = $k->last_seen_at && $k->last_seen_at->gt(now()->subMinutes(15)); @endphp
                    <li><span class="initials"><x-icon name="monitor" size="16" /></span><span class="who"><b>{{ $k->name }}</b><small>{{ $k->location ?: 'No location set' }}</small></span>
                        <span class="badge {{ $online ? 'badge-ok' : '' }}">{{ $online ? 'Online' : ($k->last_seen_at ? 'Seen '.$k->last_seen_at->diffForHumans() : 'Never used') }}</span></li>
                @endforeach
            </ul>
        @endif
    </section>
</div>
@endsection
