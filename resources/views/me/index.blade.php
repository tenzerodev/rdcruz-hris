@extends('layouts.app')
@section('title', 'Home')
@section('content')
@php
    $byType = $today->keyBy('type');
    $hm = fn (int $m) => intdiv($m, 60).'h '.str_pad($m % 60, 2, '0', STR_PAD_LEFT).'m';
    $hour = now()->hour;
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
@endphp
<div class="page-head">
    <div>
        <h1>{{ $greeting }}, {{ $employee->first_name }}</h1>
        <div class="muted">{{ now()->format('l, F j, Y') }} · {{ $employee->employee_no }} · {{ $employee->type_label }}</div>
    </div>
    @if (($employee->allow_personal || $wfh) && $enrolled && $next)
        <a class="btn btn-primary btn-lg" href="{{ route('me.punch') }}"><x-icon name="camera" /> Record {{ $employee->punchLabel($next) }}</a>
    @endif
</div>

@if ($pendingApprovals)
    <a class="action-card hot" href="{{ route('me.approvals') }}" style="margin-bottom:16px">
        <span class="ic"><x-icon name="inbox" size="20" /></span>
        <span><span class="t" style="display:block">{{ $pendingApprovals }} request(s) from your team need your action</span><span class="d">Leave, overtime and travel requests from people who report to you.</span></span>
    </a>
@endif
@foreach ($memos->take(3) as $memo)
    <a class="action-card {{ $memo->requires_ack ? 'hot' : '' }}" href="{{ route('me.memos.show', $memo) }}" style="margin-bottom:12px">
        <span class="ic"><x-icon name="megaphone" size="20" /></span>
        <span><span class="t" style="display:block">{{ $memo->kind_name }}: {{ $memo->title }}</span><span class="d">{{ $memo->requires_ack ? 'Please read and acknowledge.' : 'New from HR.' }} {{ $memo->published_at->format('M j') }}</span></span>
    </a>
@endforeach
@if ($memos->count() > 3)<p class="small" style="margin-top:-4px"><a href="{{ route('me.memos') }}">{{ $memos->count() - 3 }} more memo(s)</a></p>@endif
@if ($clearance)
    <div class="alert alert-warn" role="note"><x-icon name="list" /><div><b>Exit clearance in progress</b> — {{ $clearance->items->whereNotNull('cleared_at')->count() }} of {{ $clearance->items->count() }} offices have cleared you
        (separation {{ $clearance->separation_date->format('M j, Y') }}). Still pending: {{ $clearance->items->whereNull('cleared_at')->pluck('office')->join(', ') ?: 'none' }}.
        <a href="{{ route('print.clearance', $clearance) }}" target="_blank">View</a></div></div>
@endif
@if ($wfh)
    <div class="alert alert-ok" role="note"><x-icon name="laptop" /><div><b>Work from home today</b>{{ $wfh->reference_no ? ' ('.$wfh->reference_no.')' : '' }}.
        @if ($wfh->require_device && ! $device) Time in from your agency-issued computer — <a href="{{ route('me.devices') }}">register it first</a> if you haven't.
        @else Time in and out from {{ $device ? 'this computer' : 'any of your devices' }}.@endif
        Don't forget your <a href="{{ route('me.wfh') }}">accomplishment report</a>.</div></div>
@endif
<div class="alert alert-ok" data-install-ios hidden><x-icon name="info" /><div><b>Add the HRIS to your home screen:</b> tap the Share button in Safari, then “Add to Home Screen”.</div></div>
<button type="button" class="btn" data-install hidden style="margin-bottom:12px"><x-icon name="download" size="16" /> Install the HRIS app on this device</button>
@unless ($enrolled)<div class="alert alert-warn"><x-icon name="alert" />Your face isn't enrolled yet. Please see HR so you can time in.</div>@endunless

<div class="grid grid-2">
    <section class="card">
        <div class="card-head"><h2>Today</h2><a class="card-link" href="{{ route('me.dtr', ['month' => now()->format('Y-m')]) }}" target="_blank">My Form 48 <x-icon name="arrow" size="14" /></a></div>
        <div class="today-punches">
            @if ($employee->punchesOncePerDay())
                @php($in = $byType['am_in'] ?? $byType['pm_in'] ?? null)
                @php($out = $byType['pm_out'] ?? $byType['am_out'] ?? null)
                <div><b>{{ $in ? $in->punched_at->format('g:i A') : '—' }}</b><span>Time In</span></div>
                <div><b>{{ $out ? $out->punched_at->format('g:i A') : '—' }}</b><span>Time Out</span></div>
            @else
                @foreach (\App\Models\Punch::LABELS as $type => $label)
                    <div><b>{{ isset($byType[$type]) ? $byType[$type]->punched_at->format('g:i A') : '—' }}</b><span>{{ $label }}</span></div>
                @endforeach
            @endif
        </div>
        @unless ($employee->allow_personal || $wfh)<p class="hint" style="margin-bottom:0">You time in at the office kiosk.</p>@endunless
    </section>
    <section class="card">
        <div class="card-head"><h2>{{ now()->format('F') }} so far</h2></div>
        <div class="today-punches">
            <div><b>{{ $month['days_present'] }}</b><span>Days present</span></div>
            <div><b>{{ $month['days_absent'] }}</b><span>Absences</span></div>
            <div><b>{{ $hm($month['total_minutes']) }}</b><span>Undertime</span></div>
            <div><b>{{ $hm($month['overtime']['credited']) }}</b><span>Overtime</span></div>
        </div>
        @if ($month['overtime']['unapproved'])
            <p class="hint" style="margin-bottom:0">{{ $hm($month['overtime']['unapproved']) }} of overtime isn't credited (not approved, under 2 hours, or not allowed that week).</p>
        @endif
    </section>
</div>

<div class="section-title">What would you like to do?</div>
<div class="grid grid-4">
    <a class="action-card" href="{{ route('me.leave') }}"><span class="ic"><x-icon name="calendar" size="20" /></span><span><span class="t" style="display:block">Apply for leave</span><span class="d">See balances and file Form 6</span></span></a>
    @if ($employee->overtimeEligible())
        <a class="action-card" href="{{ route('me.overtime') }}"><span class="ic"><x-icon name="timer" size="20" /></span><span><span class="t" style="display:block">Request overtime</span><span class="d">At least 2 hours a day</span></span></a>
    @endif
    <a class="action-card" href="{{ route('me.travel') }}"><span class="ic"><x-icon name="pin" size="20" /></span><span><span class="t" style="display:block">Travel / OB</span><span class="d">Official business or travel order</span></span></a>
    <a class="action-card" href="{{ route('me.ipcr') }}"><span class="ic"><x-icon name="star" size="20" /></span><span><span class="t" style="display:block">My IPCR</span><span class="d">Targets and ratings</span></span></a>
</div>

<section class="card" style="margin-top:16px">
    <div class="card-head"><h2>Recent time records</h2>
        <form class="inline-form" method="get" action="{{ route('me.dtr') }}" target="_blank">
            <label class="sr" for="month">Month</label><input type="month" id="month" name="month" value="{{ now()->format('Y-m') }}" style="width:auto">
            <button class="btn btn-sm">Print Form 48</button>
        </form>
    </div>
    @if ($recent->isEmpty())
        <div class="empty">No time records yet.</div>
    @else
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Date</th><th>Type</th><th>Time</th><th>Recorded at</th></tr></thead>
            <tbody>
            @foreach ($recent as $p)
                <tr><td>{{ $p->work_date->format('D, M j') }}</td><td>{{ $p->label }}</td><td class="num">{{ $p->punched_at->format('g:i A') }}</td><td><span class="badge">{{ $p->source === 'wfh' ? 'WFH' : ucfirst($p->source) }}</span></td></tr>
            @endforeach
            </tbody>
        </table></div>
    @endif
    <p class="small muted" style="margin-bottom:0"><a href="{{ route('me.password') }}">Change password</a></p>
</section>
@endsection
