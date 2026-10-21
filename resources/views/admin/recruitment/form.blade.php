@extends('layouts.app')
@section('title', $vacancy->exists ? 'Edit vacancy' : 'New vacancy')
@section('content')
@php $v = fn ($f) => old($f, $vacancy->$f instanceof \Carbon\CarbonInterface ? $vacancy->$f->toDateString() : $vacancy->$f); @endphp
<div class="page-head"><div><h1>{{ $vacancy->exists ? 'Edit vacancy' : 'New vacancy' }}</h1><div class="muted">Qualification standards come from the CSC-approved QS for the position.</div></div>
    <a class="btn" href="{{ $vacancy->exists ? route('admin.recruitment.show', $vacancy) : route('admin.recruitment.index') }}">Back</a></div>
<form method="post" action="{{ $vacancy->exists ? route('admin.recruitment.update', $vacancy) : route('admin.recruitment.store') }}">@csrf @if ($vacancy->exists) @method('put') @endif
<div class="grid grid-2">
    <section class="card">
        <h2>Position</h2>
        <div class="field"><label for="position_id">Plantilla item</label><select id="position_id" name="position_id"><option value="">— Not a plantilla item (e.g. COS) —</option>
            @foreach ($positions as $p)<option value="{{ $p->id }}" @selected($v('position_id') == $p->id)>{{ $p->item_no }} — {{ $p->title }}</option>@endforeach</select></div>
        <div class="row">
            <div class="field"><label for="title">Position title</label><input type="text" id="title" name="title" value="{{ $v('title') }}" required></div>
            <div class="field"><label for="item_no">Item no.</label><input type="text" id="item_no" name="item_no" value="{{ $v('item_no') }}"></div>
        </div>
        <div class="row">
            <div class="field"><label for="salary_grade">Salary grade</label><input type="number" id="salary_grade" name="salary_grade" min="1" max="33" value="{{ $v('salary_grade') }}"></div>
            <div class="field"><label for="monthly_salary">Monthly salary (₱)</label><input type="number" step="0.01" id="monthly_salary" name="monthly_salary" value="{{ $v('monthly_salary') }}"></div>
        </div>
        <div class="row">
            <div class="field"><label for="employment_type">Employment</label><select id="employment_type" name="employment_type">@foreach (config('hris.employment_types') as $k => $t)<option value="{{ $k }}" @selected($v('employment_type') === $k)>{{ $t['label'] }}</option>@endforeach</select></div>
            <div class="field"><label for="slots">Slots</label><input type="number" id="slots" name="slots" min="1" value="{{ $v('slots') }}" required></div>
        </div>
        <div class="field"><label for="place_of_assignment">Place of assignment</label><input type="text" id="place_of_assignment" name="place_of_assignment" value="{{ $v('place_of_assignment') }}"></div>
        <div class="row">
            <div class="field"><label for="posted_on">Posting date</label><input type="date" id="posted_on" name="posted_on" value="{{ $v('posted_on') }}"></div>
            <div class="field"><label for="closing_on">Deadline</label><input type="date" id="closing_on" name="closing_on" value="{{ $v('closing_on') }}"><div class="hint">CSC: at least 10 calendar days after posting.</div></div>
        </div>
        <label class="check"><input type="checkbox" name="public" value="1" @checked($v('public'))> Show on the public careers page (untick for internal postings)</label>
    </section>
    <section class="card">
        <h2>Qualification standards</h2>
        @foreach (['education' => 'Education', 'training' => 'Training', 'experience' => 'Experience', 'eligibility' => 'Eligibility'] as $f => $l)
            <div class="field"><label for="{{ $f }}">{{ $l }}</label><textarea id="{{ $f }}" name="{{ $f }}" rows="2">{{ $v($f) }}</textarea></div>
        @endforeach
        <div class="field"><label for="competencies">Competencies</label><textarea id="competencies" name="competencies" rows="3">{{ $v('competencies') }}</textarea></div>
        <div class="field"><label for="instructions">Instructions to applicants</label><textarea id="instructions" name="instructions" rows="3" placeholder="e.g. Address your letter to the HRMPSB Chair...">{{ $v('instructions') }}</textarea></div>
        <button class="btn btn-primary">Save</button>
    </section>
</div>
</form>
@endsection
