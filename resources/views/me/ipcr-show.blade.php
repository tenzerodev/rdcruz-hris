@extends('layouts.app')
@section('title', 'IPCR — '.$ipcr->employee->full_name)
@section('content')
@php
    $cats = config('hris.ipcr.categories');
    $n = fn ($v) => $v === null ? '' : number_format($v, 2);
    $sel = fn (string $name, $value) => '<select name="'.$name.'" style="width:64px"><option value=""></option>'.collect(range(5, 1))->map(fn ($k) => '<option'.((string) $value === (string) $k ? ' selected' : '').'>'.$k.'</option>')->join('').'</select>';
@endphp
<div class="page-head">
    <div><h1>IPCR · {{ $ipcr->period->name }}</h1>
        <div class="muted">{{ $ipcr->employee->full_name }} · {{ $ipcr->employee->position }} · Rater: {{ $ipcr->rater?->full_name ?? 'not set' }} · <span class="badge badge-info">{{ $ipcr->status_label }}</span></div></div>
    <div class="actions"><a class="btn" href="{{ route('print.ipcr', $ipcr) }}" target="_blank">Print</a><a class="btn" href="{{ auth()->user()->canSee('performance') ? route('admin.ipcr.index', ['period' => $ipcr->performance_period_id]) : route('me.ipcr') }}">Back</a></div>
</div>
@if ($ipcr->status === 'draft' && $ipcr->comments)<div class="alert alert-warn">Returned by your rater: {{ $ipcr->comments }}</div>@endif
@if ($mode === 'view' && $ipcr->status === 'targets_approved' && $ipcr->period->status === 'planning')<div class="alert alert-ok">Targets approved. You can enter accomplishments once HR opens the rating phase.</div>@endif

@if ($mode === 'targets')
<form method="post" action="{{ route('me.ipcr.save', $ipcr) }}" class="card">
    @csrf
    <h2>Targets</h2>
    <p class="hint">List your outputs (MFO/PAP) and success indicators (targets + measures). Weights: @foreach (config('hris.ipcr.weights') as $k => $w){{ $cats[$k] }} {{ $w * 100 }}%@if (! $loop->last), @endif @endforeach.</p>
    <div data-repeat="items">
        @php $items = old('items', $ipcr->items->map->only('category', 'output', 'success_indicator')->all()) ?: [['category' => 'core']]; @endphp
        @foreach ($items as $i => $item)
            <div class="row" data-row style="grid-template-columns: 170px 1fr 1.4fr;align-items:start;border-bottom:1px solid var(--border);padding-bottom:8px;margin-bottom:8px">
                <div class="field"><label>Category</label><select name="items[{{ $i }}][category]">@foreach ($cats as $k => $l)<option value="{{ $k }}" @selected(($item['category'] ?? '') === $k)>{{ $l }}</option>@endforeach</select></div>
                <div class="field"><label>Output (MFO / PAP)</label><input type="text" name="items[{{ $i }}][output]" value="{{ $item['output'] ?? '' }}"></div>
                <div class="field"><label>Success indicator (targets + measures)</label><textarea name="items[{{ $i }}][success_indicator]" rows="2">{{ $item['success_indicator'] ?? '' }}</textarea></div>
            </div>
        @endforeach
    </div>
    <div class="actions" style="justify-content:flex-start">
        <button type="button" class="btn btn-sm" data-add="items">Add output</button>
        <button class="btn">Save draft</button>
        <button class="btn btn-primary" name="submit" value="1" onclick="return confirm('Submit your targets to your rater?')">Submit targets</button>
    </div>
</form>
@else
<form method="post" action="{{ $mode === 'self' ? route('me.ipcr.save', $ipcr) : route('me.ipcr.review', $ipcr) }}">
    @csrf
    <section class="card table-wrap">
        <table class="table">
            <thead><tr><th style="width:18%">Output</th><th style="width:24%">Success indicator</th><th>Actual accomplishment</th>
                <th class="num" title="Self-rating Q/E/T">Self Q · E · T</th><th class="num">Rating Q · E · T</th><th class="num">Ave.</th><th>Remarks</th></tr></thead>
            <tbody>
            @foreach ($cats as $cat => $catLabel)
                @php $rows = $ipcr->items->where('category', $cat); @endphp
                @continue($rows->isEmpty())
                <tr><td colspan="7" style="background:var(--surface-2)"><b>{{ $catLabel }}</b> <span class="small muted">({{ config("hris.ipcr.weights.$cat") * 100 }}%)</span></td></tr>
                @foreach ($rows as $item)
                    <tr>
                        <td>{{ $item->output }}</td>
                        <td class="small">{{ $item->success_indicator }}</td>
                        <td>@if ($mode === 'self')<textarea name="items[{{ $item->id }}][accomplishment]" rows="2" style="min-width:180px">{{ old("items.{$item->id}.accomplishment", $item->accomplishment) }}</textarea>@else<span class="small">{{ $item->accomplishment }}</span>@endif</td>
                        <td class="num">@if ($mode === 'self'){!! $sel("items[{$item->id}][self_q]", $item->self_q) !!}{!! $sel("items[{$item->id}][self_e]", $item->self_e) !!}{!! $sel("items[{$item->id}][self_t]", $item->self_t) !!}@else{{ $item->self_q ?? '–' }} · {{ $item->self_e ?? '–' }} · {{ $item->self_t ?? '–' }}@endif</td>
                        <td class="num">@if ($mode === 'rate'){!! $sel("items[{$item->id}][q]", $item->q ?? $item->self_q) !!}{!! $sel("items[{$item->id}][e]", $item->e ?? $item->self_e) !!}{!! $sel("items[{$item->id}][t]", $item->t ?? $item->self_t) !!}@else{{ $item->q ?? '–' }} · {{ $item->e ?? '–' }} · {{ $item->t ?? '–' }}@endif</td>
                        <td class="num">{{ $n($item->average()) }}</td>
                        <td>@if ($mode === 'rate')<input type="text" name="items[{{ $item->id }}][remarks]" value="{{ $item->remarks }}" style="min-width:100px">@else<span class="small">{{ $item->remarks }}</span>@endif</td>
                    </tr>
                @endforeach
            @endforeach
            </tbody>
        </table>
        <p class="small" style="margin-top:12px">
            Self-rating: <b>{{ $n($self['final']) ?: '—' }}</b> {{ $self['adjectival'] }}
            &nbsp;·&nbsp; Final rating: <b>{{ $ipcr->final_rating ? $n($ipcr->final_rating).' — '.$ipcr->adjectival : '—' }}</b>
        </p>
        <p class="hint">Rating scale: 5 Outstanding, 4 Very Satisfactory, 3 Satisfactory, 2 Unsatisfactory, 1 Poor. Q = quality, E = efficiency, T = timeliness; leave a dimension blank if it doesn't apply.</p>
    </section>

    @if ($mode === 'self')
        <div class="actions" style="justify-content:flex-start;margin-top:12px">
            <button class="btn">Save</button>
            <button class="btn btn-primary" name="submit" value="1" onclick="return confirm('Submit your accomplishments and self-rating to your rater?')">Submit for rating</button>
        </div>
    @elseif ($mode === 'review_targets')
        <section class="card" style="margin-top:12px">
            <div class="field"><label for="comments">Comments (required if returning)</label><textarea id="comments" name="comments" rows="2"></textarea></div>
            <div class="actions" style="justify-content:flex-start">
                <button class="btn btn-primary" name="decision" value="approve">Approve targets</button>
                <button class="btn btn-danger" name="decision" value="return">Return for revision</button>
            </div>
        </section>
    @elseif ($mode === 'rate')
        <section class="card" style="margin-top:12px">
            <div class="grid grid-2">
                <div class="field"><label for="comments">Comments and recommendations</label><textarea id="comments" name="comments" rows="3">{{ $ipcr->comments }}</textarea></div>
                <div class="field"><label for="development_plan">Development plan</label><textarea id="development_plan" name="development_plan" rows="3">{{ $ipcr->development_plan }}</textarea></div>
            </div>
            <button class="btn btn-primary" onclick="return confirm('Submit your rating?')">Submit rating</button>
        </section>
    @elseif ($ipcr->comments || $ipcr->development_plan)
        <section class="card" style="margin-top:12px">
            @if ($ipcr->comments)<p><b>Comments:</b> {{ $ipcr->comments }}</p>@endif
            @if ($ipcr->development_plan)<p><b>Development plan:</b> {{ $ipcr->development_plan }}</p>@endif
        </section>
    @endif
</form>
@endif
@endsection
@push('scripts')
<script>
document.querySelectorAll('[data-add]').forEach((btn) => btn.addEventListener('click', () => {
  const box = document.querySelector(`[data-repeat="${btn.dataset.add}"]`);
  const rows = box.querySelectorAll('[data-row]');
  const clone = rows[rows.length - 1].cloneNode(true);
  clone.querySelectorAll('input,textarea').forEach((i) => { i.value = ''; });
  clone.querySelectorAll('[name]').forEach((i) => { i.name = i.name.replace(/\[\d+\]/, `[${rows.length}]`); });
  box.appendChild(clone);
}));
</script>
@endpush
