@extends('layouts.app')
@section('title', 'Overtime')
@section('content')
<div class="page-head">
    <div><h1>Overtime</h1><div class="muted">
        @if ($requiresApproval)
            Overtime counts only on dates covered by an overtime authority below.
        @else
            All overtime rendered is counted (approval not required — change in Settings).
        @endif
    </div></div>
    <x-import-button key="overtime" />
</div>

@if ($pending->isNotEmpty())
<section class="card" style="margin-bottom:16px">
    <h2>Requests waiting for approval ({{ $pending->count() }})</h2>
    <div class="table-wrap"><table class="table"><tbody>
    @foreach ($pending as $o)
        <tr><td><b>{{ $o->employee->full_name }}</b><div class="small">{{ $o->date_from->format('D, M j') }}{{ $o->date_to->ne($o->date_from) ? ' – '.$o->date_to->format('D, M j') : '' }} · up to {{ $o->max_minutes_per_day / 60 }} h/day</div><div class="small muted">{{ $o->purpose }}</div></td>
            <td><form class="inline-form" method="post" action="{{ route('admin.overtime.decide', $o) }}">@csrf
                <div class="field"><label class="sr" for="ref-{{ $o->id }}">Reference</label><input type="text" id="ref-{{ $o->id }}" name="reference_no" placeholder="Office order no." style="width:150px"></div>
                <button class="btn btn-sm btn-primary" name="decision" value="approve">Approve</button>
                <button class="btn btn-sm btn-danger" name="decision" value="disapprove">Disapprove</button>
            </form></td></tr>
    @endforeach
    </tbody></table></div>
</section>
@endif
<div class="grid grid-2">
    <section class="card">
        <h2>Overtime report</h2>
        <form method="get" action="{{ route('admin.overtime.report') }}" target="_blank">
            <div class="row">
                <div class="field"><label for="r-month">Month</label><input type="month" id="r-month" name="month" value="{{ $month->format('Y-m') }}" required></div>
                <div class="field"><label for="r-office">Office</label>
                    <select id="r-office" name="office"><option value="">All offices</option>@foreach ($offices as $o)<option>{{ $o }}</option>@endforeach</select></div>
            </div>
            <div class="field"><label for="r-employee">Employee</label>
                <select id="r-employee" name="employee_id"><option value="">Everyone with overtime</option>
                    @foreach ($employees as $e)<option value="{{ $e->id }}">{{ $e->full_name }}</option>@endforeach</select></div>
            <div class="actions" style="justify-content:flex-start">
                <button class="btn btn-primary" name="format" value="html">Open report</button>
                <button class="btn" name="format" value="csv">Download CSV</button>
            </div>
        </form>
    </section>

    <section class="card">
        <h2>Add overtime authority</h2>
        <form method="post" action="{{ route('admin.overtime.store') }}">
            @csrf
            <div class="field"><label for="employee_ids">Employees</label>
                <select id="employee_ids" name="employee_ids[]" multiple size="6" required>
                    @foreach ($employees as $e)<option value="{{ $e->id }}" @selected(in_array($e->id, old('employee_ids', [])))>{{ $e->full_name }} — {{ $e->office }}</option>@endforeach
                </select><div class="hint">Hold Ctrl/⌘ to pick several.</div></div>
            <div class="row">
                <div class="field"><label for="date_from">From</label><input type="date" id="date_from" name="date_from" value="{{ old('date_from') }}" required></div>
                <div class="field"><label for="date_to">To</label><input type="date" id="date_to" name="date_to" value="{{ old('date_to') }}" required></div>
                <div class="field"><label for="max_hours_per_day">Max hours/day</label><input type="number" id="max_hours_per_day" name="max_hours_per_day" step="0.5" min="0.5" max="16" value="{{ old('max_hours_per_day') }}" placeholder="No limit"></div>
            </div>
            <div class="field"><label for="purpose">Purpose / work to be done</label><input type="text" id="purpose" name="purpose" value="{{ old('purpose') }}" required></div>
            <div class="field"><label for="reference_no">Office order / memo no.</label><input type="text" id="reference_no" name="reference_no" value="{{ old('reference_no') }}"></div>
            <button class="btn btn-primary">Save authority</button>
        </form>
    </section>
</div>

<section class="card" style="margin-top:16px">
    <div class="page-head" style="margin-bottom:8px">
        <h2 style="margin:0">Authorities covering {{ $month->format('F Y') }}</h2>
        <form class="inline-form" method="get"><div class="field"><label for="month" class="sr">Month</label><input type="month" id="month" name="month" value="{{ $month->format('Y-m') }}"></div><button class="btn btn-sm">Show</button></form>
    </div>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Employee</th><th>Dates</th><th>Limit</th><th>Purpose</th><th>Reference</th><th>Approved by</th><th></th></tr></thead>
        <tbody>
        @forelse ($authorizations as $a)
            <tr>
                <td>{{ $a->employee->full_name }}</td>
                <td class="num">{{ $a->date_from->format('M j') }}{{ $a->date_to->ne($a->date_from) ? ' – '.$a->date_to->format('M j, Y') : ', '.$a->date_from->format('Y') }}</td>
                <td>{{ $a->max_minutes_per_day ? rtrim(rtrim(number_format($a->max_minutes_per_day / 60, 1), '0'), '.').' h/day' : '—' }}</td>
                <td>{{ $a->purpose }}</td>
                <td>{{ $a->reference_no }}</td>
                <td class="small">{{ $a->approver?->name }}</td>
                <td><form method="post" action="{{ route('admin.overtime.destroy', $a) }}" class="actions" onsubmit="return confirm('Remove this overtime authority?')">@csrf @method('delete')<button class="btn btn-sm btn-danger">Remove</button></form></td>
            </tr>
        @empty
            <tr><td colspan="7" class="muted">No overtime authorities for this month.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</section>
@endsection
