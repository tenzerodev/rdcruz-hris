@extends('layouts.app')
@section('title', 'OPCR / DPCR')
@section('content')
<div class="page-head">
    <div><h1>Office &amp; division performance (OPCR / DPCR)</h1><div class="muted">The office or division head sets the targets and reports accomplishments; the Performance Management Team approves and rates.</div></div>
    @if ($periods->isNotEmpty())
        <form method="get"><select name="period" onchange="this.form.submit()" aria-label="Rating period">@foreach ($periods as $p)<option value="{{ $p->id }}" @selected($period?->id === $p->id)>{{ $p->name }}</option>@endforeach</select></form>
    @endif
</div>
@if (! $period)
    <section class="card"><p class="empty">Create a rating period on the <a href="{{ route('admin.ipcr.index') }}">IPCR page</a> first.</p></section>
@else
<div class="grid" style="grid-template-columns: minmax(0, 2fr) minmax(260px, 1fr)">
    <section class="card">
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Office / division</th><th>Head</th><th>Status</th><th class="num">Rating</th><th class="num">IPCRs (avg)</th></tr></thead>
            <tbody>
            @forelse ($ratings as $r)
                @php $s = $r->ipcrSummary(); @endphp
                <tr>
                    <td><a href="{{ route('performance.show', $r) }}"><b>{{ $r->office }}</b></a><div class="small muted">{{ $r->level_name }}</div></td>
                    <td class="small">{{ $r->head?->full_name ?? '—' }}</td>
                    <td><span class="badge {{ in_array($r->status, ['draft', 'rated'], true) ? 'badge-warn' : ($r->status === 'final' ? 'badge-ok' : 'badge-info') }}">{{ $r->status_label }}</span></td>
                    <td class="num">{{ $r->final_rating ? number_format($r->final_rating, 2) : '—' }}<div class="small muted">{{ $r->adjectival }}</div></td>
                    <td class="num">{{ $s['average'] !== null ? number_format($s['average'], 2) : '—' }} <span class="small muted">({{ $s['count'] }})</span>
                        @if ($r->final_rating && $s['average'] !== null && $s['average'] > $r->final_rating)<div><span class="badge badge-warn" title="Average IPCR is higher than the office rating">Above office rating</span></div>@endif</td>
                </tr>
            @empty <tr><td colspan="5" class="empty">None yet for {{ $period->name }}.</td></tr> @endforelse
            </tbody>
        </table></div>
        <p class="hint">SPMS: the average of the employees' IPCR ratings should not be higher than their office's OPCR rating. Offices are matched by the Office field on each 201 file.</p>
    </section>
    @unless ($readOnly)
    <section class="card">
        <h2>Add an office or division</h2>
        <form method="post" action="{{ route('admin.office-ratings.store') }}">@csrf
            <input type="hidden" name="performance_period_id" value="{{ $period->id }}">
            <div class="field"><label for="level">Level</label><select id="level" name="level">@foreach (\App\Models\OfficeRating::LEVELS as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
            <div class="field"><label for="office">Office / division</label><input type="text" id="office" name="office" list="offices" required>
                <datalist id="offices">@foreach ($offices as $o)<option value="{{ $o }}">@endforeach</datalist></div>
            <div class="field"><label for="head_id">Head</label><select id="head_id" name="head_id"><option value=""></option>@foreach ($employees as $e)<option value="{{ $e->id }}">{{ $e->full_name }}</option>@endforeach</select>
                <div class="hint">The head is notified and can fill in targets and accomplishments.</div></div>
            <button class="btn btn-primary">Add</button>
        </form>
    </section>
    @endunless
</div>
@endif
@endsection
