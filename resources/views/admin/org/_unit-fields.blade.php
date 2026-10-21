@php $p = 'u'.($u->id ?? 'new'); @endphp
<div class="row">
    <div class="field"><label for="{{ $p }}-level">Level</label><select id="{{ $p }}-level" name="level">@foreach (\App\Models\OrgUnit::LEVELS as $k => $l)<option value="{{ $k }}" @selected(old('level', $u->level) === $k)>{{ $l }}</option>@endforeach</select></div>
    <div class="field"><label for="{{ $p }}-code">Code</label><input type="text" id="{{ $p }}-code" name="code" value="{{ old('code', $u->code) }}" placeholder="e.g. FAD"></div>
</div>
<div class="field"><label for="{{ $p }}-name">Name</label><input type="text" id="{{ $p }}-name" name="name" value="{{ old('name', $u->name) }}" placeholder="Finance and Administration Department" required></div>
<div class="field"><label for="{{ $p }}-parent">Under</label><select id="{{ $p }}-parent" name="parent_id"><option value="">— Top level —</option>
    @foreach ($units as $opt)@if ($opt->id !== ($u->id ?? null))<option value="{{ $opt->id }}" @selected(old('parent_id', $u->parent_id) == $opt->id)>{{ $opt->level_label }}: {{ $opt->name }}</option>@endif @endforeach</select></div>
<div class="row">
    <div class="field"><label for="{{ $p }}-head">Head</label><select id="{{ $p }}-head" name="head_employee_id"><option value="">— None / vacant —</option>
        @foreach ($employees as $e)<option value="{{ $e->id }}" @selected(old('head_employee_id', $u->head_employee_id) == $e->id)>{{ $e->full_name }}</option>@endforeach</select></div>
    <div class="field"><label for="{{ $p }}-title">Head's title</label><input type="text" id="{{ $p }}-title" name="head_title" value="{{ old('head_title', $u->head_title) }}" placeholder="Division Chief / OIC"></div>
    <div class="field"><label for="{{ $p }}-sort">Order</label><input type="number" id="{{ $p }}-sort" name="sort" min="0" value="{{ old('sort', $u->sort ?? 0) }}"></div>
</div>
