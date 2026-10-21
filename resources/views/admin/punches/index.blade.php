@extends('layouts.app')
@section('title', 'Attendance')
@section('content')
<div class="page-head">
    <h1>Attendance log</h1>
    <div class="actions">
        <x-import-button key="attendance" />
        <a class="btn" href="{{ route('admin.punches.export', request()->query()) }}">Export CSV</a>
        <a class="btn btn-primary" href="{{ route('admin.punches.create') }}">Add manual punch</a>
    </div>
</div>
<div class="card">
    <form class="inline-form" method="get" style="margin-bottom:12px">
        <div class="field"><label for="from">From</label><input type="date" id="from" name="from" value="{{ $from->toDateString() }}"></div>
        <div class="field"><label for="to">To</label><input type="date" id="to" name="to" value="{{ $to->toDateString() }}"></div>
        <div class="field" style="min-width:220px"><label for="employee_id">Employee</label>
            <select id="employee_id" name="employee_id"><option value="">All</option>
                @foreach ($employees as $e)<option value="{{ $e->id }}" @selected(request('employee_id') == $e->id)>{{ $e->full_name }}</option>@endforeach
            </select></div>
        <div class="field"><label for="source">Source</label>
            <select id="source" name="source"><option value="">All</option>
                @foreach (['kiosk' => 'Kiosk', 'personal' => 'Personal device', 'wfh' => 'Work from home', 'manual' => 'Manual'] as $s => $l)<option value="{{ $s }}" @selected(request('source') === $s)>{{ $l }}</option>@endforeach
            </select></div>
        <button class="btn">Filter</button>
    </form>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Photo</th><th>Employee</th><th>Date</th><th>Type</th><th>Time</th><th>Source</th><th>Checks</th><th></th></tr></thead>
        <tbody>
        @forelse ($punches as $p)
            <tr>
                <td>@if ($p->snapshot_path)<a href="{{ route('admin.punches.snapshot', $p) }}" target="_blank"><img class="thumb" src="{{ route('admin.punches.snapshot', $p) }}" alt="Snapshot" loading="lazy"></a>@endif</td>
                <td>{{ $p->employee->full_name }}<div class="small muted">{{ $p->employee->employee_no }}</div></td>
                <td>{{ $p->work_date->format('M j, D') }}</td>
                <td>{{ $p->label }}</td>
                <td class="num">{{ $p->punched_at->format('g:i:s A') }}</td>
                <td><span class="badge">{{ $p->source }}</span>
                    <div class="small muted">{{ $p->kiosk?->name ?? $p->device?->name ?? ($p->source === 'manual' ? 'by '.($p->creator?->name ?? '?') : '') }}</div></td>
                <td class="small">
                    @if ($p->source !== 'manual')
                        <span class="badge {{ $p->liveness ? 'badge-ok' : 'badge-warn' }}">{{ $p->liveness ? 'live' : 'no blink' }}</span>
                        <span class="muted" title="Face distance (lower is a closer match)">{{ $p->distance !== null ? number_format($p->distance, 2) : '' }}</span>
                    @endif
                    @if ($p->latitude)<a href="https://www.google.com/maps?q={{ $p->latitude }},{{ $p->longitude }}" target="_blank" rel="noopener">map</a>@endif
                    @if ($p->remarks)<div class="muted">{{ $p->remarks }}</div>@endif
                </td>
                <td><div class="actions">
                    <a class="btn btn-sm" href="{{ route('admin.punches.edit', $p) }}">Edit</a>
                    <form method="post" action="{{ route('admin.punches.destroy', $p) }}" onsubmit="return confirm('Delete this punch?')">@csrf @method('delete')<button class="btn btn-sm btn-danger">Delete</button></form>
                </div></td>
            </tr>
        @empty
            <tr><td colspan="8" class="muted">No punches for this filter.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    <div class="pagination">{{ $punches->links() }}</div>
</div>
@endsection
