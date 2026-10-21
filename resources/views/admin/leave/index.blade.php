@extends('layouts.app')
@section('title', 'Leave')
@section('content')
<div class="page-head">
    <div><h1>Leave applications</h1><div class="muted">Supervisors recommend; HR approves here and the credits are charged.</div></div>
    <div class="actions"><x-import-button key="leave_history" /><a class="btn" href="{{ route('admin.leave.posting') }}">Leave card posting</a></div>
</div>
<nav class="tabs">
    @foreach (['open' => 'To act on', 'recommended' => 'Recommended', 'pending' => 'Awaiting supervisor', 'approved' => 'Approved', 'disapproved' => 'Disapproved', 'cancelled' => 'Cancelled', 'all' => 'All'] as $k => $l)
        <a href="{{ route('admin.leave.index', ['status' => $k] + request()->only('employee_id')) }}" @if ($status === $k) aria-current="page" @endif>{{ $l }}</a>
    @endforeach
</nav>
<div class="grid" style="grid-template-columns: minmax(0, 2fr) minmax(280px, 1fr)">
    <section class="card table-wrap">
        <table class="table">
            <thead><tr><th>Employee</th><th>Leave</th><th>Dates</th><th>Days</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse ($applications as $l)
                <tr>
                    <td><a href="{{ route('admin.employees.show', [$l->employee, 'tab' => 'leave']) }}">{{ $l->employee->full_name }}</a><div class="small muted">{{ $l->employee->type_label }}</div></td>
                    <td>{{ $l->type_name }}@if ($l->reason)<div class="small muted">{{ $l->reason }}</div>@endif</td>
                    <td class="num small">{{ $l->date_from->format('M j') }}{{ $l->date_to->ne($l->date_from) ? '–'.$l->date_to->format('M j') : '' }}, {{ $l->date_from->format('Y') }}{{ $l->half_day ? ' ('.strtoupper($l->half_day).')' : '' }}</td>
                    <td class="num">{{ $l->days + 0 }}@if ($l->days_without_pay)<div class="small muted">{{ $l->days_without_pay + 0 }} w/o pay</div>@endif</td>
                    <td>@include('admin.leave._status', ['status' => $l->status])
                        @if ($l->recommender)<div class="small muted">rec. {{ $l->recommender->name }}</div>@endif
                        @if ($l->remarks)<div class="small muted">{{ $l->remarks }}</div>@endif</td>
                    <td><div class="actions">
                        <a class="btn btn-sm" href="{{ route('print.leave', $l) }}" target="_blank">Form 6</a>
                        @if ($l->isOpen())
                            <form method="post" action="{{ route('admin.leave.decide', $l) }}">@csrf<input type="hidden" name="decision" value="approve"><button class="btn btn-sm btn-primary">Approve</button></form>
                            <form method="post" action="{{ route('admin.leave.decide', $l) }}" onsubmit="const r = prompt('Reason for disapproval'); if (!r) return false; this.remarks.value = r;">@csrf<input type="hidden" name="decision" value="disapprove"><input type="hidden" name="remarks"><button class="btn btn-sm btn-danger">Disapprove</button></form>
                        @elseif ($l->status === 'approved')
                            <form method="post" action="{{ route('admin.leave.decide', $l) }}" onsubmit="return confirm('Cancel this approved leave and restore the credits?')">@csrf<input type="hidden" name="decision" value="cancel"><button class="btn btn-sm">Cancel</button></form>
                        @endif
                    </div></td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted">Nothing here.</td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="pagination">{{ $applications->links() }}</div>
    </section>
    <section class="card">
        <h2>File leave for an employee</h2>
        <form method="post" action="{{ route('admin.leave.store') }}">@csrf
            <div class="field"><label for="employee_id">Employee</label><select id="employee_id" name="employee_id" required><option value="">Choose…</option>
                @foreach ($employees as $e)<option value="{{ $e->id }}" @selected(old('employee_id') == $e->id)>{{ $e->full_name }}</option>@endforeach</select></div>
            @include('me._leave-fields', ['types' => collect(config('hris.leave_types'))->map(fn ($t) => $t['name'])->all()])
            <label class="check"><input type="checkbox" name="approve_now" value="1" checked> Approve now</label>
            <button class="btn btn-primary" style="margin-top:10px">File leave</button>
        </form>
    </section>
</div>
@endsection
