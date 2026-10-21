@extends('layouts.app')
@section('title', $rating->level_name.' — '.$rating->office)
@section('content')
@php
    $cats = config('hris.ipcr.categories');
    $n = fn ($v) => $v === null ? '' : number_format($v, 2);
    $sel = fn (string $name, $value) => '<select name="'.$name.'" style="width:64px"><option value=""></option>'.collect(range(5, 1))->map(fn ($k) => '<option'.((string) $value === (string) $k ? ' selected' : '').'>'.$k.'</option>')->join('').'</select>';
@endphp
<div class="page-head">
    <div><h1>{{ $rating->level_name }} · {{ $rating->office }}</h1>
        <div class="muted">{{ $rating->period->name }} · Head: {{ $rating->head?->full_name ?? 'not set' }} · <span class="badge badge-info">{{ $rating->status_label }}</span></div></div>
    <div class="actions"><a class="btn" href="{{ route('performance.print', $rating) }}" target="_blank">Print</a>
        <a class="btn" href="{{ auth()->user()->canSee('performance') ? route('admin.office-ratings.index', ['period' => $rating->performance_period_id]) : route('me.ipcr') }}">Back</a></div>
</div>
@if ($mode === 'view' && $rating->status === 'targets_approved' && $rating->period->status === 'planning')
    <div class="alert alert-ok"><x-icon name="check" />Targets approved. Accomplishments can be entered once HR opens the rating phase of {{ $rating->period->name }}.</div>
@endif

@if ($mode === 'targets')
<form method="post" action="{{ route('performance.save', $rating) }}" class="card">@csrf
    <h2>Targets</h2>
    <p class="hint">The office's major final outputs, programs and projects, with success indicators (targets + measures), budget and who is accountable.</p>
    <div data-repeat="items">
        @php $items = old('items', $rating->items->map->only('category', 'output', 'success_indicator', 'budget', 'accountable')->all()) ?: [['category' => 'core']]; @endphp
        @foreach ($items as $i => $item)
            <div class="row" data-row style="align-items:start;border-bottom:1px solid var(--border);padding-bottom:8px;margin-bottom:8px">
                <div class="field" style="max-width:170px"><label>Category</label><select name="items[{{ $i }}][category]">@foreach ($cats as $k => $l)<option value="{{ $k }}" @selected(($item['category'] ?? '') === $k)>{{ $l }}</option>@endforeach</select></div>
                <div class="field"><label>Output (MFO / PAP)</label><input type="text" name="items[{{ $i }}][output]" value="{{ $item['output'] ?? '' }}"></div>
                <div class="field" style="flex:1.4"><label>Success indicator</label><textarea name="items[{{ $i }}][success_indicator]" rows="2">{{ $item['success_indicator'] ?? '' }}</textarea></div>
                <div class="field" style="max-width:140px"><label>Budget (₱)</label><input type="number" step="0.01" min="0" name="items[{{ $i }}][budget]" value="{{ $item['budget'] ?? '' }}"></div>
                <div class="field"><label>Accountable</label><input type="text" name="items[{{ $i }}][accountable]" value="{{ $item['accountable'] ?? '' }}" placeholder="Division / persons"></div>
            </div>
        @endforeach
    </div>
    <div class="actions" style="justify-content:flex-start">
        <button type="button" class="btn btn-sm" data-add="items">Add output</button>
        <button class="btn btn-primary">Save targets</button>
    </div>
</form>
@else
<form method="post" action="{{ route('performance.save', $rating) }}">@csrf
<section class="card table-wrap">
    <table class="table">
        <thead><tr><th style="width:17%">Output</th><th style="width:22%">Success indicator</th><th class="num">Budget</th><th>Accountable</th><th>Actual accomplishment</th><th class="num">Q · E · T</th><th class="num">Ave.</th><th>Remarks</th></tr></thead>
        <tbody>
        @foreach ($cats as $cat => $catLabel)
            @php $rows = $rating->items->where('category', $cat); @endphp
            @continue($rows->isEmpty())
            <tr><td colspan="8" style="background:var(--surface-2)"><b>{{ $catLabel }}</b> <span class="small muted">({{ config("hris.ipcr.weights.$cat") * 100 }}%)</span></td></tr>
            @foreach ($rows as $item)
                <tr>
                    <td>{{ $item->output }}</td><td class="small">{{ $item->success_indicator }}</td>
                    <td class="num small">{{ $item->budget ? number_format($item->budget, 2) : '' }}</td><td class="small">{{ $item->accountable }}</td>
                    <td>@if ($mode === 'accomplish')<textarea name="items[{{ $item->id }}][accomplishment]" rows="2" style="min-width:180px">{{ $item->accomplishment }}</textarea>@else<span class="small">{{ $item->accomplishment }}</span>@endif</td>
                    <td class="num">@if ($mode === 'accomplish' && $pmt){!! $sel("items[{$item->id}][q]", $item->q) !!}{!! $sel("items[{$item->id}][e]", $item->e) !!}{!! $sel("items[{$item->id}][t]", $item->t) !!}@else{{ $item->q ?? '–' }} · {{ $item->e ?? '–' }} · {{ $item->t ?? '–' }}@endif</td>
                    <td class="num">{{ $n($item->average()) }}</td>
                    <td>@if ($mode === 'accomplish' && $pmt)<input type="text" name="items[{{ $item->id }}][remarks]" value="{{ $item->remarks }}" style="min-width:100px">@else<span class="small">{{ $item->remarks }}</span>@endif</td>
                </tr>
            @endforeach
        @endforeach
        </tbody>
    </table>
    <p class="small" style="margin-top:12px">Computed: <b>{{ $n($computed['final']) ?: '—' }}</b> {{ $computed['adjectival'] }} · Final rating: <b>{{ $rating->final_rating ? $n($rating->final_rating).' — '.$rating->adjectival : '—' }}</b>
        · IPCRs of {{ $rating->office }}: {{ $ipcrs['count'] }} final, average {{ $ipcrs['average'] !== null ? $n($ipcrs['average']) : '—' }}
        @if ($ipcrs['above'])<span class="badge badge-warn">{{ $ipcrs['above'] }} above the office rating</span>@endif</p>
    @if ($mode === 'accomplish' && $pmt)
        <div class="field"><label for="comments">Assessment / comments (PMT)</label><textarea id="comments" name="comments" rows="2">{{ $rating->comments }}</textarea></div>
    @elseif ($rating->comments)<p class="small"><b>PMT comments:</b> {{ $rating->comments }}</p>@endif
    @if ($mode === 'accomplish')
        <div class="actions" style="justify-content:flex-start">
            <button class="btn">Save</button>
            @if ($pmt)<button class="btn btn-primary" name="submit" value="1" onclick="return confirm('Submit this rating?')">Submit rating</button>@endif
        </div>
    @endif
</section>
</form>
@endif

@if ($pmt)
<div class="actions" style="justify-content:flex-start;margin-top:12px">
    @if ($rating->status === 'draft')
        <form method="post" action="{{ route('performance.decide', $rating) }}">@csrf<input type="hidden" name="decision" value="approve"><button class="btn btn-primary" onclick="return confirm('Approve these targets?')">Approve targets</button></form>
    @elseif ($rating->status === 'targets_approved')
        <form method="post" action="{{ route('performance.decide', $rating) }}">@csrf<input type="hidden" name="decision" value="return"><button class="btn">Return targets for revision</button></form>
    @elseif ($rating->status === 'rated')
        <form method="post" action="{{ route('performance.decide', $rating) }}">@csrf<input type="hidden" name="decision" value="finalize"><button class="btn btn-primary">Finalize</button></form>
        <form method="post" action="{{ route('performance.decide', $rating) }}">@csrf<input type="hidden" name="decision" value="reopen"><button class="btn">Reopen rating</button></form>
    @elseif ($rating->status === 'final')
        <form method="post" action="{{ route('performance.decide', $rating) }}" onsubmit="return confirm('Reopen a final rating?')">@csrf<input type="hidden" name="decision" value="reopen"><button class="btn">Reopen</button></form>
    @endif
    @if ($rating->status !== 'final')
        <form method="post" action="{{ route('performance.destroy', $rating) }}" onsubmit="return confirm('Delete this {{ $rating->level_name }}?')">@csrf @method('delete')<button class="btn btn-danger">Delete</button></form>
    @endif
</div>
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
