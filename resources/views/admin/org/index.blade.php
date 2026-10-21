@extends('layouts.app')
@section('title', 'Organizational structure')
@section('content')
<div class="page-head">
    <div><h1>Organizational structure</h1><div class="muted">The approved structure and any interim structures, with their offices, departments, divisions and units.</div></div>
    <x-import-button key="org_units" />
</div>

@if ($current)
    <a class="action-card" href="{{ route('admin.org.show', $current) }}" style="margin-bottom:16px">
        <span class="ic" style="background:var(--ok-soft);color:var(--ok)"><x-icon name="check" size="20" /></span>
        <span><span class="t" style="display:block">In effect today: {{ $current->name }}</span>
            <span class="d">{{ $current->kind_label }}{{ $current->effective_to ? ' until '.$current->effective_to->format('M j, Y') : '' }}{{ $current->reference ? ' · '.$current->reference : '' }}</span></span>
    </a>
@else
    <div class="alert alert-warn"><x-icon name="alert" /><div>No structure is active yet. Create the approved structure below, add its units, then activate it.</div></div>
@endif

<div class="grid" style="grid-template-columns: minmax(0, 2fr) minmax(280px, 1fr); align-items:start">
    <section class="card">
        <h2>Structures</h2>
        <div class="table-wrap"><table class="table"><tbody>
        @forelse ($structures as $s)
            <tr>
                <td><a href="{{ route('admin.org.show', $s) }}"><b>{{ $s->name }}</b></a>
                    <div class="small muted">{{ $s->kind_label }} · {{ $s->units_count }} units · {{ $s->assignments_count }} people{{ $s->reference ? ' · '.$s->reference : '' }}</div></td>
                <td class="small">{{ $s->effective_from?->format('M j, Y') ?? '—' }}{{ $s->effective_to ? ' – '.$s->effective_to->format('M j, Y') : '' }}</td>
                <td><span class="badge {{ $s->status === 'active' ? 'badge-ok' : ($s->status === 'draft' ? 'badge-warn' : '') }}">{{ \App\Models\OrgStructure::STATUSES[$s->status] }}</span>
                    @if ($current?->id === $s->id)<span class="badge badge-info">in effect</span>@endif</td>
                <td><div class="actions"><a class="btn btn-sm" href="{{ route('admin.org.show', $s) }}">Open</a><a class="btn btn-sm" href="{{ route('print.org', $s) }}" target="_blank">Chart</a></div></td>
            </tr>
        @empty <tr><td class="empty">No structures yet.</td></tr> @endforelse
        </tbody></table></div>
    </section>
    <section class="card">
        <h2>New structure</h2>
        <form method="post" action="{{ route('admin.org.store') }}">@csrf
            <div class="field"><label for="kind">Kind</label><select id="kind" name="kind">
                <option value="approved">Approved structure</option><option value="interim" @selected($current)>Interim structure</option></select></div>
            <div class="field"><label for="name">Name</label><input type="text" id="name" name="name" placeholder="Interim structure — Q4 2026" required></div>
            <div class="row">
                <div class="field"><label for="effective_from">Effective from</label><input type="date" id="effective_from" name="effective_from"></div>
                <div class="field"><label for="effective_to">Until (interim)</label><input type="date" id="effective_to" name="effective_to"></div>
            </div>
            <div class="field"><label for="reference">Approval reference</label><input type="text" id="reference" name="reference" placeholder="Board Resolution No. … / Office Order No. …"></div>
            <div class="field"><label for="copy_from">Start from</label><select id="copy_from" name="copy_from"><option value="">Blank</option>
                @foreach ($structures as $s)<option value="{{ $s->id }}" @selected($current?->id === $s->id)>Copy of {{ $s->name }}</option>@endforeach</select>
                <div class="hint">An interim structure usually starts as a copy of the approved one; then move, add or rename units.</div></div>
            <button class="btn btn-primary">Create draft</button>
        </form>
    </section>
</div>
<section class="card" style="margin-top:16px">
    <h2>How structures work</h2>
    <ul class="small" style="margin:0;padding-left:18px;line-height:1.8">
        <li><b>Levels:</b> Office → Department → Division → Unit. A unit can sit directly under a department or office; not every branch needs every level.</li>
        <li><b>Approved</b> structure: only one is active at a time. Activating a new one archives the old.</li>
        <li><b>Interim</b> structure: in effect between its dates (e.g. while a reorganization awaits approval). When it ends, the approved structure applies again automatically.</li>
        <li>People's <b>office</b> on the DTR and reports follows their unit in the structure in effect today. Use <b>Update supervisors</b> on a structure to set everyone's approver to their unit head.</li>
    </ul>
</section>
@endsection
