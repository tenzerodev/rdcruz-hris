@extends('layouts.app')
@section('title', 'Personal Data Sheet')
@section('content')
@php
    $g = fn (string $path) => old('pds.'.$path, data_get($p, $path));
    $f = fn (string $path) => 'pds['.str_replace('.', '][', $path).']';
    $lists = \App\Support\Pds::LISTS;
    $labels = [
        'children' => ['name' => 'Full name', 'birth_date' => 'Birth date'],
        'work_experience' => ['from' => 'From', 'to' => 'To', 'position' => 'Position', 'company' => 'Department / agency / company', 'monthly_salary' => 'Monthly salary', 'salary_grade' => 'SG-step', 'status' => 'Status', 'government' => 'Gov\'t (Y/N)'],
        'voluntary' => ['organization' => 'Organization & address', 'from' => 'From', 'to' => 'To', 'hours' => 'Hours', 'position' => 'Position / nature of work'],
        'references' => ['name' => 'Name', 'address' => 'Address', 'telephone' => 'Tel. no.'],
    ];
    $dates = ['birth_date', 'from', 'to'];
@endphp
<div class="page-head">
    <div><h1>Personal Data Sheet</h1><div class="muted">{{ $employee->full_name }} · CSC Form 212 (Revised 2017). Name, birth date, government numbers, education, eligibility and trainings come from the 201 file.</div></div>
    <div class="actions"><a class="btn" href="{{ route('print.pds', $employee) }}" target="_blank">Print PDS</a><a class="btn" href="{{ $back }}">Back</a></div>
</div>
<form method="post" action="{{ $action }}" class="stack">
    @csrf @method('put')
    <section class="card">
        <h2>I. Personal information</h2>
        <div class="row">
            <div class="field"><label>Place of birth</label><input type="text" name="{{ $f('personal.place_of_birth') }}" value="{{ $g('personal.place_of_birth') }}"></div>
            <div class="field"><label>Citizenship</label><select name="{{ $f('personal.citizenship') }}">@foreach (['Filipino', 'Dual citizenship'] as $c)<option @selected($g('personal.citizenship') === $c)>{{ $c }}</option>@endforeach</select></div>
            <div class="field"><label>Dual by</label><select name="{{ $f('personal.dual_by') }}"><option value=""></option>@foreach (['birth', 'naturalization'] as $c)<option value="{{ $c }}" @selected($g('personal.dual_by') === $c)>{{ ucfirst($c) }}</option>@endforeach</select></div>
            <div class="field"><label>Country (if dual)</label><input type="text" name="{{ $f('personal.dual_country') }}" value="{{ $g('personal.dual_country') }}"></div>
        </div>
        <div class="row">
            <div class="field"><label>Height (m)</label><input type="text" name="{{ $f('personal.height') }}" value="{{ $g('personal.height') }}" inputmode="decimal"></div>
            <div class="field"><label>Weight (kg)</label><input type="text" name="{{ $f('personal.weight') }}" value="{{ $g('personal.weight') }}" inputmode="decimal"></div>
            <div class="field"><label>Blood type</label><input type="text" name="{{ $f('personal.blood_type') }}" value="{{ $g('personal.blood_type') }}"></div>
            <div class="field"><label>Telephone</label><input type="text" name="{{ $f('personal.telephone') }}" value="{{ $g('personal.telephone') }}"></div>
            <div class="field"><label>UMID ID no.</label><input type="text" name="{{ $f('personal.umid_no') }}" value="{{ $g('personal.umid_no') }}"></div>
            <div class="field"><label>PhilSys no. (PSN)</label><input type="text" name="{{ $f('personal.philsys_no') }}" value="{{ $g('personal.philsys_no') }}"></div>
        </div>
        @foreach (['residential' => 'Residential address', 'permanent' => 'Permanent address'] as $key => $title)
            <h3 class="small" style="margin:10px 0 6px">{{ $title }}</h3>
            <div class="row">
                @foreach (['house' => 'House/block/lot no.', 'street' => 'Street', 'subdivision' => 'Subdivision/village', 'barangay' => 'Barangay', 'city' => 'City/municipality', 'province' => 'Province', 'zip' => 'ZIP code'] as $k => $l)
                    <div class="field"><label>{{ $l }}</label><input type="text" name="{{ $f("$key.$k") }}" value="{{ $g("$key.$k") }}"></div>
                @endforeach
            </div>
        @endforeach
    </section>

    <section class="card">
        <h2>II. Family background</h2>
        <h3 class="small" style="margin:0 0 6px">Spouse</h3>
        <div class="row">
            @foreach (['surname' => 'Surname', 'first' => 'First name', 'middle' => 'Middle name', 'ext' => 'Ext.', 'occupation' => 'Occupation', 'employer' => 'Employer / business name', 'business_address' => 'Business address', 'telephone' => 'Telephone'] as $k => $l)
                <div class="field"><label>{{ $l }}</label><input type="text" name="{{ $f("spouse.$k") }}" value="{{ $g("spouse.$k") }}"></div>
            @endforeach
        </div>
        <div class="grid grid-2">
            <div><h3 class="small" style="margin:0 0 6px">Father</h3><div class="row">
                @foreach (['surname' => 'Surname', 'first' => 'First name', 'middle' => 'Middle name', 'ext' => 'Ext.'] as $k => $l)<div class="field"><label>{{ $l }}</label><input type="text" name="{{ $f("father.$k") }}" value="{{ $g("father.$k") }}"></div>@endforeach
            </div></div>
            <div><h3 class="small" style="margin:0 0 6px">Mother's maiden name</h3><div class="row">
                @foreach (['surname' => 'Surname', 'first' => 'First name', 'middle' => 'Middle name'] as $k => $l)<div class="field"><label>{{ $l }}</label><input type="text" name="{{ $f("mother.$k") }}" value="{{ $g("mother.$k") }}"></div>@endforeach
            </div></div>
        </div>
    </section>

    @foreach (['children' => 'Children', 'work_experience' => 'V. Work experience before / outside this agency (service here is added from the service record)', 'voluntary' => 'VI. Voluntary work / involvement in civic, non-government, people, voluntary organizations', 'references' => 'References (not related by consanguinity or affinity)'] as $list => $title)
        <section class="card">
            <h2>{{ $title }}</h2>
            <div data-repeat="{{ $list }}">
                @php $rows = old("pds.$list", data_get($p, $list, [])) ?: [[]]; @endphp
                @foreach ($rows as $i => $row)
                    <div class="row" data-row>
                        @foreach ($lists[$list] as $k)
                            <div class="field"><label>{{ $labels[$list][$k] }}</label><input type="{{ in_array($k, $dates) ? 'date' : 'text' }}" name="pds[{{ $list }}][{{ $i }}][{{ $k }}]" value="{{ $row[$k] ?? '' }}"></div>
                        @endforeach
                    </div>
                @endforeach
            </div>
            <button type="button" class="btn btn-sm" data-add="{{ $list }}">Add row</button>
        </section>
    @endforeach

    <section class="card">
        <h2>VIII. Other information</h2>
        <div class="grid grid-2">
            @foreach (['skills' => 'Special skills and hobbies', 'recognitions' => 'Non-academic distinctions / recognition', 'memberships' => 'Membership in association/organization'] as $k => $l)
                <div class="field"><label>{{ $l }} <span class="hint">(one per line)</span></label><textarea name="pds[{{ $k }}]" rows="4">{{ old("pds.$k", implode("\n", data_get($p, $k, []))) }}</textarea></div>
            @endforeach
        </div>
    </section>

    <section class="card">
        <h2>Questions 34–40</h2>
        @foreach (\App\Support\Pds::QUESTIONS as $q => $text)
            <div style="padding:10px 0;border-bottom:1px solid var(--border)">
                <div class="small">{{ $text }}</div>
                <div class="inline-form" style="margin-top:6px">
                    <label class="check"><input type="radio" name="pds[questions][{{ $q }}][answer]" value="yes" @checked($g("questions.$q.answer") === 'yes')> Yes</label>
                    <label class="check"><input type="radio" name="pds[questions][{{ $q }}][answer]" value="no" @checked($g("questions.$q.answer") === 'no')> No</label>
                    <div class="field" style="flex:1;min-width:200px"><label class="sr" for="q{{ $q }}">If yes, give details</label><input type="text" id="q{{ $q }}" name="pds[questions][{{ $q }}][details]" value="{{ $g("questions.$q.details") }}" placeholder="If yes, give details"></div>
                </div>
            </div>
        @endforeach
    </section>

    <section class="card">
        <h2>Government-issued ID</h2>
        <div class="row">
            <div class="field"><label>ID type</label><input type="text" name="{{ $f('gov_id.type') }}" value="{{ $g('gov_id.type') }}" placeholder="Passport, GSIS, SSS, PRC, Driver's License…"></div>
            <div class="field"><label>ID / license / passport no.</label><input type="text" name="{{ $f('gov_id.number') }}" value="{{ $g('gov_id.number') }}"></div>
            <div class="field"><label>Date / place of issuance</label><input type="text" name="{{ $f('gov_id.issued') }}" value="{{ $g('gov_id.issued') }}"></div>
        </div>
    </section>
    <div><button class="btn btn-primary btn-lg">Save PDS</button></div>
</form>
@endsection
@push('scripts')
<script>
document.querySelectorAll('[data-add]').forEach((btn) => btn.addEventListener('click', () => {
  const box = document.querySelector(`[data-repeat="${btn.dataset.add}"]`);
  const rows = box.querySelectorAll('[data-row]');
  const clone = rows[rows.length - 1].cloneNode(true);
  const next = rows.length;
  clone.querySelectorAll('input').forEach((i) => { i.value = ''; i.name = i.name.replace(/\[\d+\]/, `[${next}]`); });
  box.appendChild(clone);
  clone.querySelector('input').focus();
}));
</script>
@endpush
