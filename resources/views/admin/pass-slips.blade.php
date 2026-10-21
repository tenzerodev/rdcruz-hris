@extends('layouts.app')
@section('title', 'Pass slips')
@section('content')
<div class="page-head"><div><h1>Pass slips</h1><div class="muted">Supervisors approve their team's pass slips. Approve here only for employees without a supervisor, and record or correct times from the guard's logbook.</div></div></div>
<nav class="tabs">
    @foreach (['pending' => 'Pending', 'approved' => 'Approved', 'disapproved' => 'Disapproved', 'cancelled' => 'Cancelled', 'all' => 'All'] as $k => $l)
        <a href="{{ route('admin.pass-slips.index', ['status' => $k, 'date' => $date]) }}" @if ($status === $k) aria-current="page" @endif>{{ $l }}</a>
    @endforeach
</nav>
<div class="grid" style="grid-template-columns: minmax(0, 2fr) minmax(280px, 1fr)">
    <section class="card">
        <form method="get" class="row" style="align-items:flex-end"><input type="hidden" name="status" value="{{ $status }}">
            <div class="field"><label for="f_date">Date</label><input type="date" id="f_date" name="date" value="{{ $date }}"></div>
            <div class="field"><button class="btn">Filter</button></div></form>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Employee</th><th>Date &amp; time</th><th>Where / why</th><th>Status</th><th></th></tr></thead><tbody>
            @forelse ($slips as $p)
                <tr>
                    <td><b>{{ $p->employee->full_name }}</b><div class="small muted">{{ $p->type_name }}</div></td>
                    <td class="small">{{ $p->date->format('D, M j, Y') }}<div>{{ $p->times_label }}</div>
                        @if ($p->actual_out)<div class="muted">Planned {{ $p->time_out }}–{{ $p->time_back ?? '…' }}</div>@endif</td>
                    <td>{{ $p->destination }}<div class="small muted">{{ $p->purpose }}</div></td>
                    <td>@include('admin.leave._status', ['status' => $p->status])@if ($p->approver)<div class="small muted">{{ $p->approver->name }}</div>@endif</td>
                    <td>@unless ($readOnly)<div class="actions">
                        @if ($p->status === 'pending')
                            <form method="post" action="{{ route('admin.pass-slips.decide', $p) }}">@csrf<input type="hidden" name="decision" value="approve"><button class="btn btn-sm btn-primary">Approve</button></form>
                            <form method="post" action="{{ route('admin.pass-slips.decide', $p) }}">@csrf<input type="hidden" name="decision" value="disapprove"><button class="btn btn-sm btn-danger">Disapprove</button></form>
                        @elseif ($p->status === 'approved')
                            <details class="inline-form"><summary class="btn btn-sm">Times</summary>
                                <form method="post" action="{{ route('admin.pass-slips.times', $p) }}" class="card" style="margin-top:8px;min-width:220px">@csrf @method('put')
                                    <div class="row"><div class="field"><label for="ao{{ $p->id }}">Out</label><input type="time" id="ao{{ $p->id }}" name="actual_out" value="{{ $p->actual_out }}"></div>
                                    <div class="field"><label for="ab{{ $p->id }}">Back</label><input type="time" id="ab{{ $p->id }}" name="actual_back" value="{{ $p->actual_back }}"></div></div>
                                    <button class="btn btn-sm btn-primary">Save</button></form>
                            </details>
                            <form method="post" action="{{ route('admin.pass-slips.decide', $p) }}" onsubmit="return confirm('Cancel this pass slip?')">@csrf<input type="hidden" name="decision" value="cancel"><button class="btn btn-sm">Cancel</button></form>
                        @endif
                    </div>@endunless</td>
                </tr>
            @empty <tr><td colspan="5" class="empty">Nothing here.</td></tr> @endforelse
            </tbody></table></div>
        <div class="pagination">{{ $slips->links() }}</div>
    </section>
    <section class="card">
        <h2>Record a pass slip</h2>
        @if ($readOnly)<p class="muted">Read-only.</p>@else
        <form method="post" action="{{ route('admin.pass-slips.store') }}">@csrf
            <div class="field"><label for="ps_emp">Employee</label><select id="ps_emp" name="employee_id" required><option value=""></option>@foreach ($employees as $e)<option value="{{ $e->id }}">{{ $e->full_name }}</option>@endforeach</select></div>
            @include('me._pass-slip-fields')
            <div class="row"><div class="field"><label for="ps_ao">Actual out</label><input type="time" id="ps_ao" name="actual_out"></div><div class="field"><label for="ps_ab">Actual back</label><input type="time" id="ps_ab" name="actual_back"></div></div>
            <button class="btn btn-primary">Record (approved)</button>
        </form>
        <p class="hint">On the DTR: an official slip with no return time counts the rest of the day as present. A personal slip's time out is added to undertime.</p>
        @endif
    </section>
</div>
@endsection
