@extends('layouts.app')
@section('title', 'Leave')
@section('content')
@php $n = fn ($v) => rtrim(rtrim(number_format($v, 3), '0'), '.') ?: '0'; @endphp
<div class="page-head"><h1>Leave</h1></div>
<div class="grid grid-2">
    <div class="stack">
        <section class="card">
            <h2>My balances</h2>
            @if ($balances)
                <div class="today-punches">
                    @foreach (config('hris.balances') as $k => $l)<div><b>{{ $n($balances[$k]) }}</b><span>{{ $k === 'COC' ? 'COC (hours)' : $l }}</span></div>@endforeach
                </div>
                <p class="hint">Balances include this month's postings by HR. Pending applications are not deducted yet.</p>
            @elseif ($wellness)
                <div class="today-punches">
                    <div><b>{{ $n($wellness['remaining']) }}</b><span>Wellness leave left ({{ now()->year }})</span></div>
                    <div><b>{{ $n($wellness['used']) }}</b><span>Used or pending</span></div>
                    <div><b>{{ $n($wellness['entitled']) }}</b><span>Entitled this year</span></div>
                </div>
                <p class="hint">{{ $employee->type_label }}: {{ config('hris.wellness.full_days') }} days of wellness leave with pay each year ({{ config('hris.wellness.new_days') }} days in your first year if you started from January to June). Unused days don't carry over. Other absences can be filed as "Others" so they're excused on your DTR.</p>
            @else
                <p class="muted">{{ $employee->type_label }} personnel don't earn leave credits. You can still file leave so the days are excused on your DTR.</p>
            @endif
        </section>
        <section class="card">
            <h2>My applications</h2>
            <div class="table-wrap"><table class="table"><tbody>
            @forelse ($applications as $l)
                <tr>
                    <td>{{ $l->type_name }}<div class="small muted">{{ $l->date_from->format('M j') }}{{ $l->date_to->ne($l->date_from) ? '–'.$l->date_to->format('M j') : '' }}, {{ $l->date_from->format('Y') }} · {{ $l->days + 0 }} day(s){{ $l->days_without_pay ? ' · '.($l->days_without_pay + 0).' without pay' : '' }}</div>
                        @if ($l->remarks)<div class="small muted">{{ $l->remarks }}</div>@endif</td>
                    <td>@include('admin.leave._status', ['status' => $l->status])</td>
                    <td><div class="actions">
                        <a class="btn btn-sm" href="{{ route('print.leave', $l) }}" target="_blank">Form 6</a>
                        @if ($l->isOpen())<form method="post" action="{{ route('me.leave.cancel', $l) }}" onsubmit="return confirm('Cancel this application?')">@csrf<button class="btn btn-sm">Cancel</button></form>@endif
                    </div></td>
                </tr>
            @empty <tr><td class="muted">No leave applications yet.</td></tr> @endforelse
            </tbody></table></div>
        </section>
    </div>
    <section class="card">
        <h2>Apply for leave</h2>
        @unless ($employee->supervisor_id)<div class="alert alert-warn">You have no supervisor set, so HR will act on this directly.</div>@endunless
        <form method="post" action="{{ route('me.leave') }}">@csrf
            @include('me._leave-fields', ['types' => $types])
            <button class="btn btn-primary" style="margin-top:12px">Submit application</button>
        </form>
        <p class="hint">Weekends and holidays are not counted. Vacation leave should be filed at least 5 days ahead; sick leave is filed on return to work.</p>
    </section>
</div>
@endsection
