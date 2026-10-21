@extends('layouts.app')
@section('title', 'Overtime')
@section('content')
<div class="page-head"><h1>Overtime requests</h1></div>
@unless ($employee->overtimeEligible())
    <div class="alert alert-warn">{{ $employee->type_label }} personnel can't claim overtime.</div>
@else
<div class="grid grid-2">
    <section class="card">
        <h2>Request overtime</h2>
        <form method="post" action="{{ route('me.overtime') }}">@csrf
            <div class="row">
                <div class="field"><label for="date_from">From</label><input type="date" id="date_from" name="date_from" value="{{ old('date_from') }}" required></div>
                <div class="field"><label for="date_to">To</label><input type="date" id="date_to" name="date_to" value="{{ old('date_to') }}" required></div>
                <div class="field"><label for="hours">Hours per day</label><input type="number" id="hours" name="hours" step="0.5" min="{{ $minHours }}" max="16" value="{{ old('hours', $minHours) }}" required></div>
            </div>
            <div class="field"><label for="purpose">Work to be done</label><input type="text" id="purpose" name="purpose" value="{{ old('purpose') }}" required></div>
            <button class="btn btn-primary">Send to supervisor</button>
        </form>
    </section>
    <section class="card">
        <h2>Rules</h2>
        <ul class="small">
            <li>Overtime is credited only when approved, up to the approved hours per day.</li>
            <li>At least <b>{{ $minHours + 0 }} hours</b> in a day is needed to claim it.</li>
            <li>On work days it starts after your required time-out (flexi: 8 hours + lunch after your time-in).</li>
            <li><b>Weekend overtime</b> is not allowed if you were on leave or absent any day Monday–Friday of that week.</li>
            <li>Approved overtime earns COC hours on your leave card at month-end (×1.5 on weekends and holidays).</li>
        </ul>
    </section>
</div>
@endunless
<section class="card" style="margin-top:16px">
    <h2>My requests</h2>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Dates</th><th>Hours/day</th><th>Purpose</th><th>Status</th><th></th></tr></thead><tbody>
        @forelse ($requests as $r)
            <tr><td class="num">{{ $r->date_from->format('M j') }}{{ $r->date_to->ne($r->date_from) ? '–'.$r->date_to->format('M j') : '' }}, {{ $r->date_from->format('Y') }}</td>
                <td class="num">{{ $r->max_minutes_per_day ? $r->max_minutes_per_day / 60 : '—' }}</td><td>{{ $r->purpose }}@if ($r->remarks)<div class="small muted">{{ $r->remarks }}</div>@endif</td>
                <td>@include('admin.leave._status', ['status' => $r->status])</td>
                <td>@if ($r->status === 'pending')<form method="post" action="{{ route('me.overtime.cancel', $r) }}">@csrf<button class="btn btn-sm">Cancel</button></form>@endif</td></tr>
        @empty <tr><td colspan="5" class="muted">No requests yet.</td></tr> @endforelse
        </tbody></table></div>
</section>
@endsection
