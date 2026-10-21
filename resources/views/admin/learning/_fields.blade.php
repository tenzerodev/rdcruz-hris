@php $p = $program ?? null; $v = fn ($f, $d = null) => old($f, $p?->$f instanceof \Carbon\CarbonInterface ? $p->$f->toDateString() : ($p?->$f ?? $d)); @endphp
<input type="hidden" name="year" value="{{ $v('year', $year ?? now()->year) }}">
<div class="field"><label for="lp_title">Training title</label><input type="text" id="lp_title" name="title" value="{{ $v('title', $prefill['title'] ?? null) }}" required></div>
<div class="field"><label for="lp_comp">Competency addressed</label><input type="text" id="lp_comp" name="competency" value="{{ $v('competency', $prefill['competency'] ?? null) }}"></div>
<div class="row">
    <div class="field"><label for="lp_type">Type (PDS)</label><select id="lp_type" name="type"><option value=""></option>@foreach (\App\Models\Training::TYPES as $k => $l)<option value="{{ $k }}" @selected($v('type') === $k)>{{ $l }}</option>@endforeach</select></div>
    <div class="field"><label for="lp_mode">Mode</label><select id="lp_mode" name="mode">@foreach (\App\Models\TrainingProgram::MODES as $k => $l)<option value="{{ $k }}" @selected($v('mode', 'in_house') === $k)>{{ $l }}</option>@endforeach</select></div>
</div>
<div class="field"><label for="lp_provider">Provider / sponsor</label><input type="text" id="lp_provider" name="provider" value="{{ $v('provider') }}" placeholder="e.g. CSC Civil Service Institute"></div>
<div class="row">
    <div class="field"><label for="lp_q">Quarter</label><select id="lp_q" name="quarter"><option value=""></option>@foreach ([1, 2, 3, 4] as $q)<option value="{{ $q }}" @selected($v('quarter') == $q)>Q{{ $q }}</option>@endforeach</select></div>
    <div class="field"><label for="lp_from">From</label><input type="date" id="lp_from" name="date_from" value="{{ $v('date_from') }}"></div>
    <div class="field"><label for="lp_to">To</label><input type="date" id="lp_to" name="date_to" value="{{ $v('date_to') }}"></div>
</div>
<div class="row">
    <div class="field"><label for="lp_hours">Hours</label><input type="number" id="lp_hours" name="hours" min="1" value="{{ $v('hours') }}"></div>
    <div class="field"><label for="lp_slots">Slots</label><input type="number" id="lp_slots" name="slots" min="1" value="{{ $v('slots') }}"></div>
    <div class="field"><label for="lp_budget">Budget (₱)</label><input type="number" step="0.01" min="0" id="lp_budget" name="budget" value="{{ $v('budget') }}"></div>
</div>
<div class="field"><label for="lp_notes">Notes</label><input type="text" id="lp_notes" name="notes" value="{{ $v('notes') }}"></div>
