@extends('layouts.app')
@section('title', $s->name)
@section('content')
@php
    $levels = \App\Models\OrgUnit::LEVELS;
    $nextLevel = $edit ? (array_keys($levels)[\App\Models\OrgUnit::rank($edit->level) + 1] ?? 'unit') : 'department';
@endphp
<div class="page-head">
    <div><h1>{{ $s->name }}</h1>
        <div class="muted">{{ $s->kind_label }} · <span class="badge {{ $s->status === 'active' ? 'badge-ok' : 'badge-warn' }}">{{ \App\Models\OrgStructure::STATUSES[$s->status] }}</span>
            @if ($isCurrent)<span class="badge badge-info">in effect today</span>@endif
            · {{ $s->effective_from?->format('M j, Y') ?? 'no start date' }}{{ $s->effective_to ? ' – '.$s->effective_to->format('M j, Y') : '' }}</div></div>
    <div class="actions">
        <a class="btn" href="{{ route('print.org', $s) }}" target="_blank"><x-icon name="file" size="16" /> Print chart</a>
        <form method="post" action="{{ route('admin.org.sync', $s) }}" onsubmit="return confirm('Set each person’s immediate supervisor to the head of their unit (or the next unit up for heads)?')">@csrf<button class="btn">Update supervisors</button></form>
        @if ($s->status !== 'active')
            <form method="post" action="{{ route('admin.org.update', $s) }}" onsubmit="return confirm('Activate this structure?{{ $s->kind === 'approved' ? ' The current approved structure will be archived.' : '' }}')">@csrf @method('put')<input type="hidden" name="action" value="activate"><button class="btn btn-primary">Activate</button></form>
        @else
            <form method="post" action="{{ route('admin.org.update', $s) }}" onsubmit="return confirm('Archive this structure?')">@csrf @method('put')<input type="hidden" name="action" value="archive"><button class="btn">Archive</button></form>
        @endif
    </div>
</div>

<div class="grid" style="grid-template-columns: minmax(0, 1.4fr) minmax(300px, 1fr); align-items:start">
    <section class="card">
        <div class="card-head"><h2>Structure</h2><span class="small muted">Click a unit to edit it and its people</span></div>
        @if ($tree)
            <ul class="org-tree">@foreach ($tree as $node)@include('admin.org._node', ['node' => $node])@endforeach</ul>
        @else
            <div class="empty">No units yet. Add the top-level offices or departments on the right.</div>
        @endif
        @if ($unassigned->isNotEmpty())
            <details class="more" style="margin-top:12px"><summary>{{ $unassigned->count() }} active personnel not assigned in this structure</summary>
                <p class="small">{{ $unassigned->map->full_name->join('; ') }}</p></details>
        @endif
    </section>

    <div class="stack">
        @if ($edit)
            <section class="card">
                <div class="card-head"><h2>{{ $edit->level_label }}: {{ $edit->name }}</h2><a class="card-link" href="{{ route('admin.org.show', $s) }}">Close</a></div>
                <form method="post" action="{{ route('admin.org.units.update', [$s, $edit]) }}">@csrf @method('put')
                    @include('admin.org._unit-fields', ['u' => $edit])
                    <button class="btn btn-primary">Save unit</button>
                </form>
                <h3 class="small" style="margin:18px 0 6px">People in this unit ({{ $members->count() }})</h3>
                <ul class="people">
                    @forelse ($members as $m)
                        <li><span class="who"><b>{{ $m->employee->display_name }}</b><small>{{ $m->designation ?: $m->employee->position }}{{ $edit->head_employee_id === $m->employee_id ? ' · Head' : '' }}</small></span>
                            <form method="post" action="{{ route('admin.org.unassign', [$s, $m]) }}">@csrf @method('delete')<button class="icon-btn" title="Remove from unit" aria-label="Remove"><x-icon name="x" size="16" /></button></form></li>
                    @empty <li class="empty">No one assigned yet.</li> @endforelse
                </ul>
                <form method="post" action="{{ route('admin.org.assign', [$s, $edit]) }}" style="margin-top:10px">@csrf
                    <div class="field"><label for="employee_ids">Add people</label><select id="employee_ids" name="employee_ids[]" multiple size="6">
                        @foreach ($employees as $e)<option value="{{ $e->id }}">{{ $e->full_name }}{{ $unassigned->contains('id', $e->id) ? '' : ' (will move)' }}</option>@endforeach</select>
                        <div class="hint">Hold Ctrl/⌘ to pick several. Someone already in another unit of this structure is moved here.</div></div>
                    <div class="field"><label for="designation">Designation in this unit (optional)</label><input type="text" id="designation" name="designation" placeholder="e.g. OIC, Section Head"></div>
                    <button class="btn">Assign</button>
                </form>
                <form method="post" action="{{ route('admin.org.units.destroy', [$s, $edit]) }}" style="margin-top:16px" onsubmit="return confirm('Remove this unit? Its people become unassigned in this structure.')">@csrf @method('delete')<button class="btn btn-sm btn-danger">Remove unit</button></form>
            </section>
        @endif

        <section class="card">
            <h2>Add {{ $edit ? 'a unit under '.$edit->name : 'a unit' }}</h2>
            <form method="post" action="{{ route('admin.org.units.store', $s) }}">@csrf
                @include('admin.org._unit-fields', ['u' => new \App\Models\OrgUnit(['parent_id' => $edit?->id, 'level' => $nextLevel])])
                <button class="btn btn-primary">Add</button>
            </form>
        </section>

        <section class="card">
            <h2>Details</h2>
            <form method="post" action="{{ route('admin.org.update', $s) }}">@csrf @method('put')
                <div class="field"><label for="sname">Name</label><input type="text" id="sname" name="name" value="{{ $s->name }}" required></div>
                <div class="row">
                    <div class="field"><label for="sfrom">Effective from</label><input type="date" id="sfrom" name="effective_from" value="{{ $s->effective_from?->toDateString() }}"></div>
                    <div class="field"><label for="sto">Until</label><input type="date" id="sto" name="effective_to" value="{{ $s->effective_to?->toDateString() }}"></div>
                </div>
                <div class="field"><label for="sref">Approval reference</label><input type="text" id="sref" name="reference" value="{{ $s->reference }}"></div>
                <div class="field"><label for="snotes">Notes</label><textarea id="snotes" name="notes" rows="2">{{ $s->notes }}</textarea></div>
                <button class="btn">Save details</button>
            </form>
            @if ($s->status !== 'active')
                <form method="post" action="{{ route('admin.org.destroy', $s) }}" style="margin-top:12px" onsubmit="return confirm('Delete this structure and all its units?')">@csrf @method('delete')<button class="btn btn-sm btn-danger">Delete structure</button></form>
            @endif
        </section>
    </div>
</div>
@endsection
