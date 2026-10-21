@extends('layouts.app')
@section('title', 'IPCR')
@section('content')
<div class="page-head"><div><h1>Performance management (IPCR)</h1><div class="muted">Open a period for targets, switch it to rating at the end of the semester, then finalize ratings.</div></div></div>
<div class="grid" style="grid-template-columns: minmax(0, 2fr) minmax(260px, 1fr)">
    <section class="card">
        @if ($period)
            <div class="page-head" style="margin-bottom:8px">
                <div><h2 style="margin:0">{{ $period->name }}</h2><div class="small muted">{{ $period->starts_on->format('M j') }} – {{ $period->ends_on->format('M j, Y') }}</div></div>
                <form class="inline-form" method="post" action="{{ route('admin.ipcr.periods.update', $period) }}">@csrf @method('put')
                    <div class="field"><label for="pstatus">Phase</label><select id="pstatus" name="status">@foreach (\App\Models\PerformancePeriod::STATUSES as $k => $l)<option value="{{ $k }}" @selected($period->status === $k)>{{ $l }}</option>@endforeach</select></div>
                    <button class="btn btn-sm">Update</button></form>
            </div>
            @if ($summary->isNotEmpty())
                <p class="small">@foreach ($summary as $label => $count)<span class="badge" style="margin-right:4px">{{ $label }}: {{ $count }}</span>@endforeach</p>
            @endif
            <div class="table-wrap"><table class="table">
                <thead><tr><th>Employee</th><th>Rater</th><th>Status</th><th class="num">Rating</th><th></th></tr></thead><tbody>
                @foreach ($employees as $e)
                    @php $i = $ipcrs[$e->id] ?? null; @endphp
                    <tr><td>{{ $e->full_name }}<div class="small muted">{{ $e->office }} · {{ $e->type_label }}</div></td>
                        <td class="small">{{ $i?->rater?->full_name ?? $e->supervisor?->full_name }}</td>
                        <td>@if ($i)<span class="badge {{ $i->status === 'rated' ? 'badge-warn' : ($i->status === 'final' ? 'badge-ok' : '') }}">{{ $i->status_label }}</span>@else<span class="muted small">not started</span>@endif</td>
                        <td class="num">{{ $i?->final_rating ? number_format($i->final_rating, 2).' '.$i->adjectival : '' }}</td>
                        <td><div class="actions">
                            @if ($i)<a class="btn btn-sm" href="{{ route('me.ipcr.show', $i) }}">Open</a><a class="btn btn-sm" href="{{ route('print.ipcr', $i) }}" target="_blank">Print</a>@endif
                            @if ($i?->status === 'rated')<form method="post" action="{{ route('admin.ipcr.finalize', $i) }}">@csrf<button class="btn btn-sm btn-primary" name="decision" value="finalize">Finalize</button></form>@endif
                            @if ($i?->status === 'final')<form method="post" action="{{ route('admin.ipcr.finalize', $i) }}" onsubmit="return confirm('Send back to the rater?')">@csrf<button class="btn btn-sm" name="decision" value="reopen">Reopen</button></form>@endif
                        </div></td></tr>
                @endforeach
                </tbody></table></div>
        @else
            <p class="muted">No rating periods yet. Create one to let employees set their targets.</p>
        @endif
    </section>
    <div class="stack">
        <section class="card">
            <h2>New rating period</h2>
            <form method="post" action="{{ route('admin.ipcr.periods.store') }}">@csrf
                <div class="field"><label for="pname">Name</label><input type="text" id="pname" name="name" value="{{ now()->month <= 6 ? 'January–June '.now()->year : 'July–December '.now()->year }}" required></div>
                <div class="row">
                    <div class="field"><label for="starts_on">Start</label><input type="date" id="starts_on" name="starts_on" value="{{ now()->month <= 6 ? now()->startOfYear()->toDateString() : now()->setMonth(7)->startOfMonth()->toDateString() }}" required></div>
                    <div class="field"><label for="ends_on">End</label><input type="date" id="ends_on" name="ends_on" value="{{ now()->month <= 6 ? now()->setMonth(6)->endOfMonth()->toDateString() : now()->endOfYear()->toDateString() }}" required></div>
                </div>
                <button class="btn btn-primary">Create</button>
            </form>
        </section>
        <section class="card">
            <h2>Periods</h2>
            @foreach ($periods as $p)<div><a href="{{ route('admin.ipcr.index', ['period' => $p->id]) }}">{{ $p->name }}</a> <span class="badge">{{ \App\Models\PerformancePeriod::STATUSES[$p->status] }}</span></div>@endforeach
        </section>
    </div>
</div>
@endsection
