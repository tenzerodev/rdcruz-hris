@extends('layouts.app')
@section('title', 'Approvals')
@section('content')
<div class="page-head"><div><h1>Approvals</h1><div class="muted">Requests from the {{ $team->count() }} people who report to you.</div></div></div>
<section class="card">
    <h2>Leave to recommend ({{ $leaves->count() }})</h2>
    <div class="table-wrap"><table class="table"><tbody>
    @forelse ($leaves as $l)
        <tr><td><b>{{ $l->employee->full_name }}</b><div class="small">{{ $l->type_name }} · {{ $l->date_from->format('M j') }}{{ $l->date_to->ne($l->date_from) ? '–'.$l->date_to->format('M j') : '' }} · {{ $l->days + 0 }} day(s)</div>@if ($l->reason)<div class="small muted">{{ $l->reason }}</div>@endif</td>
            <td><div class="actions">
                <a class="btn btn-sm" href="{{ route('print.leave', $l) }}" target="_blank">Form 6</a>
                <form method="post" action="{{ route('me.approvals.leave', $l) }}">@csrf<input type="hidden" name="decision" value="recommend"><button class="btn btn-sm btn-primary">Recommend approval</button></form>
                <form method="post" action="{{ route('me.approvals.leave', $l) }}" onsubmit="const r = prompt('Reason for disapproval'); if (!r) return false; this.remarks.value = r;">@csrf<input type="hidden" name="decision" value="disapprove"><input type="hidden" name="remarks"><button class="btn btn-sm btn-danger">Disapprove</button></form>
            </div></td></tr>
    @empty <tr><td class="muted">Nothing to act on.</td></tr> @endforelse
    </tbody></table></div>
</section>
<section class="card" style="margin-top:16px">
    <h2>Overtime requests ({{ $overtime->count() }})</h2>
    <div class="table-wrap"><table class="table"><tbody>
    @forelse ($overtime as $o)
        <tr><td><b>{{ $o->employee->full_name }}</b><div class="small">{{ $o->date_from->format('D, M j') }}{{ $o->date_to->ne($o->date_from) ? ' – '.$o->date_to->format('D, M j') : '' }} · up to {{ $o->max_minutes_per_day / 60 }} h/day</div><div class="small muted">{{ $o->purpose }}</div></td>
            <td><div class="actions">
                <form method="post" action="{{ route('me.approvals.overtime', $o) }}">@csrf<input type="hidden" name="decision" value="approve"><button class="btn btn-sm btn-primary">Approve</button></form>
                <form method="post" action="{{ route('me.approvals.overtime', $o) }}">@csrf<input type="hidden" name="decision" value="disapprove"><button class="btn btn-sm btn-danger">Disapprove</button></form>
            </div></td></tr>
    @empty <tr><td class="muted">Nothing to act on.</td></tr> @endforelse
    </tbody></table></div>
</section>
<section class="card" style="margin-top:16px">
    <h2>Travel / OB ({{ $travel->count() }})</h2>
    <div class="table-wrap"><table class="table"><tbody>
    @forelse ($travel as $o)
        <tr><td><b>{{ $o->employees->map->full_name->join('; ') }}</b><div class="small">{{ $o->type_name }} — {{ $o->destination }} · {{ $o->date_from->format('M j') }}{{ $o->date_to->ne($o->date_from) ? '–'.$o->date_to->format('M j') : '' }}{{ $o->time_from ? ' · '.$o->time_from.'–'.$o->time_to : '' }}</div><div class="small muted">{{ $o->purpose }}</div></td>
            <td><div class="actions">
                <form method="post" action="{{ route('me.approvals.travel', $o) }}">@csrf<input type="hidden" name="decision" value="approve"><button class="btn btn-sm btn-primary">Approve</button></form>
                <form method="post" action="{{ route('me.approvals.travel', $o) }}">@csrf<input type="hidden" name="decision" value="disapprove"><button class="btn btn-sm btn-danger">Disapprove</button></form>
            </div></td></tr>
    @empty <tr><td class="muted">Nothing to act on.</td></tr> @endforelse
    </tbody></table></div>
</section>
<section class="card" style="margin-top:16px">
    <h2>Pass slips ({{ $passSlips->count() }})</h2>
    <div class="table-wrap"><table class="table"><tbody>
    @forelse ($passSlips as $p)
        <tr><td><b>{{ $p->employee->full_name }}</b><div class="small">{{ $p->type_name }} · {{ $p->date->format('D, M j') }} · {{ $p->times_label }} — {{ $p->destination }}</div><div class="small muted">{{ $p->purpose }}</div></td>
            <td><div class="actions">
                <form method="post" action="{{ route('me.approvals.pass-slip', $p) }}">@csrf<input type="hidden" name="decision" value="approve"><button class="btn btn-sm btn-primary">Approve</button></form>
                <form method="post" action="{{ route('me.approvals.pass-slip', $p) }}">@csrf<input type="hidden" name="decision" value="disapprove"><button class="btn btn-sm btn-danger">Disapprove</button></form>
            </div></td></tr>
    @empty <tr><td class="muted">Nothing to act on.</td></tr> @endforelse
    </tbody></table></div>
</section>
@endsection
