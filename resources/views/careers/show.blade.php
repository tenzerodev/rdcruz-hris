@extends('layouts.app')
@section('title', $vacancy->title)
@section('bare', true)
@section('content')
@include('careers._head')
<h1>{{ $vacancy->title }}</h1>
<div class="muted" style="margin-bottom:16px">{{ $vacancy->type_label }}{{ $vacancy->item_no ? ' · Item no. '.$vacancy->item_no : '' }}{{ $vacancy->salary_grade ? ' · SG '.$vacancy->salary_grade : '' }}{{ $vacancy->monthly_salary ? ' · ₱'.number_format($vacancy->monthly_salary, 2).' a month' : '' }}{{ $vacancy->place_of_assignment ? ' · '.$vacancy->place_of_assignment : '' }}</div>
<div class="grid grid-2">
    <section class="card">
        <h2>Qualification standards</h2>
        <dl class="kv">
            @foreach (['education' => 'Education', 'training' => 'Training', 'experience' => 'Experience', 'eligibility' => 'Eligibility'] as $f => $l)
                <dt>{{ $l }}</dt><dd>{!! nl2br(e($vacancy->$f ?: 'None required')) !!}</dd>
            @endforeach
            @if ($vacancy->competencies)<dt>Competencies</dt><dd>{!! nl2br(e($vacancy->competencies)) !!}</dd>@endif
        </dl>
        @if ($vacancy->instructions)<h2>How to apply</h2><p>{!! nl2br(e($vacancy->instructions)) !!}</p>@endif
        @if ($vacancy->closing_on)<p><b>Deadline:</b> {{ $vacancy->closing_on->format('F j, Y') }}</p>@endif
    </section>
    <section class="card">
        @if (! $vacancy->isAccepting())
            <h2>Applications are closed</h2><p class="muted">This position is no longer accepting applications.</p>
        @else
        <h2>Apply online</h2>
        <form method="post" action="{{ route('careers.apply', $vacancy) }}" enctype="multipart/form-data">@csrf
            <div style="position:absolute;left:-9999px" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
            <div class="row">
                <div class="field"><label for="last_name">Last name</label><input type="text" id="last_name" name="last_name" value="{{ old('last_name') }}" required></div>
                <div class="field"><label for="first_name">First name</label><input type="text" id="first_name" name="first_name" value="{{ old('first_name') }}" required></div>
                <div class="field"><label for="middle_name">Middle name</label><input type="text" id="middle_name" name="middle_name" value="{{ old('middle_name') }}"></div>
            </div>
            <div class="row">
                <div class="field"><label for="email">Email</label><input type="email" id="email" name="email" value="{{ old('email') }}" required></div>
                <div class="field"><label for="mobile">Mobile</label><input type="tel" id="mobile" name="mobile" value="{{ old('mobile') }}" required></div>
            </div>
            <div class="row">
                <div class="field"><label for="birth_date">Birth date</label><input type="date" id="birth_date" name="birth_date" value="{{ old('birth_date') }}"></div>
                <div class="field"><label for="sex">Sex</label><select id="sex" name="sex"><option value=""></option><option value="female" @selected(old('sex') === 'female')>Female</option><option value="male" @selected(old('sex') === 'male')>Male</option></select></div>
            </div>
            <div class="field"><label for="address">Address</label><input type="text" id="address" name="address" value="{{ old('address') }}"></div>
            <div class="field"><label for="current_position">Current position and employer</label><input type="text" id="current_position" name="current_position" value="{{ old('current_position') }}"></div>
            <div class="field"><label for="education">Education (degree, school, year)</label><textarea id="education" name="education" rows="2" required>{{ old('education') }}</textarea></div>
            <div class="field"><label for="experience">Relevant work experience</label><textarea id="experience" name="experience" rows="3">{{ old('experience') }}</textarea></div>
            <div class="field"><label for="training">Relevant training (title, hours)</label><textarea id="training" name="training" rows="2">{{ old('training') }}</textarea></div>
            <div class="field"><label for="eligibility">Eligibility / license</label><input type="text" id="eligibility" name="eligibility" value="{{ old('eligibility') }}"></div>
            <h3 style="margin:16px 0 6px">Documents (PDF or image, up to 5 MB each)</h3>
            @foreach (config('hris.recruitment.documents') as $k => $l)
                <div class="field"><label for="f_{{ $k }}">{{ $l }}{{ in_array($k, ['letter', 'pds'], true) ? ' *' : '' }}</label><input type="file" id="f_{{ $k }}" name="files[{{ $k }}]" accept=".pdf,.jpg,.jpeg,.png" @required(in_array($k, ['letter', 'pds'], true))></div>
            @endforeach
            <label class="check"><input type="checkbox" name="consent" value="1" required> I agree that the {{ \App\Models\Setting::get('agency_name') }} may process my personal data for this application, as described in the <a href="{{ route('privacy') }}" target="_blank">privacy notice</a>. Files of applicants not hired are deleted after the hiring is finished.</label>
            <button class="btn btn-primary" style="margin-top:12px">Submit application</button>
        </form>
        @endif
    </section>
</div>
@endsection
