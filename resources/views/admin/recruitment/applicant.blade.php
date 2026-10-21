@extends('layouts.app')
@section('title', $applicant->full_name)
@section('content')
@php $criteria = config('hris.recruitment.criteria'); $v = $applicant->vacancy; @endphp
<div class="page-head">
    <div><h1>{{ $applicant->full_name }}</h1><div class="muted">{{ $applicant->reference_no }} · applied for <a href="{{ route('admin.recruitment.show', $v) }}">{{ $v->title }}</a> on {{ $applicant->created_at->format('M j, Y') }}
        · @include('admin.recruitment._status', ['status' => $applicant->status, 'label' => $applicant->status_label])</div></div>
    <a class="btn" href="{{ route('admin.recruitment.show', $v) }}">Back to applicants</a>
</div>
<div class="grid grid-2">
    <section class="card">
        <h2>Application</h2>
        <dl class="kv">
            <dt>Email / mobile</dt><dd>{{ $applicant->email }} · {{ $applicant->mobile ?: '—' }}</dd>
            <dt>Address</dt><dd>{{ $applicant->address ?: '—' }}</dd>
            <dt>Birth date / sex</dt><dd>{{ $applicant->birth_date?->format('M j, Y') ?? '—' }} · {{ $applicant->sex ? ucfirst($applicant->sex) : '—' }}</dd>
            <dt>Current position</dt><dd>{{ $applicant->current_position ?: '—' }}@if ($applicant->employee) <a href="{{ route('admin.employees.show', $applicant->employee) }}">(201 file)</a>@endif</dd>
            @foreach (['education' => 'Education', 'experience' => 'Experience', 'training' => 'Training', 'eligibility' => 'Eligibility'] as $f => $l)
                <dt>{{ $l }}</dt><dd>{!! nl2br(e($applicant->$f ?: '—')) !!}</dd>
            @endforeach
        </dl>
        <h3 style="margin:16px 0 6px">Documents</h3>
        @if ($applicant->files_purged_at)<p class="muted small">Files deleted {{ $applicant->files_purged_at->format('M j, Y') }} (data retention).</p>@endif
        @forelse ($applicant->documents ?? [] as $i => $doc)
            <div><a href="{{ route('admin.recruitment.document', [$applicant, $i]) }}" target="_blank"><x-icon name="file" size="16" /> {{ $doc['label'] }}</a> <span class="small muted">{{ $doc['name'] }}</span></div>
        @empty <p class="muted small">None uploaded.</p> @endforelse
        <h3 style="margin:16px 0 6px">Qualification standards for the position</h3>
        <dl class="kv small">@foreach (['education' => 'Education', 'training' => 'Training', 'experience' => 'Experience', 'eligibility' => 'Eligibility'] as $f => $l)<dt>{{ $l }}</dt><dd>{{ $v->$f ?: '—' }}</dd>@endforeach</dl>
    </section>
    <div class="stack">
        @unless ($readOnly || $applicant->status === 'appointed')
        <section class="card">
            <h2>Status</h2>
            <form method="post" action="{{ route('admin.recruitment.applicant.update', $applicant) }}">@csrf @method('put')
                <div class="field"><label for="status">Status</label><select id="status" name="status">@foreach (\App\Models\Applicant::STATUSES as $k => $l)@continue($k === 'appointed')<option value="{{ $k }}" @selected($applicant->status === $k)>{{ $l }}</option>@endforeach</select></div>
                <div class="field"><label for="remarks">Remarks (e.g. why not qualified)</label><input type="text" id="remarks" name="remarks" value="{{ $applicant->remarks }}"></div>
                <input type="hidden" name="notify" value="0"><label class="check"><input type="checkbox" name="notify" value="1"> Email the applicant about this status</label>
                <button class="btn btn-primary">Save</button>
            </form>
        </section>
        @endunless
        <section class="card">
            <div class="card-head"><h2>Comparative assessment</h2><b>{{ $scores['total'] !== null ? number_format($scores['total'], 2).' / 100' : 'Not scored' }}</b></div>
            @if ($applicant->assessments->isNotEmpty())
            <div class="table-wrap"><table class="table small">
                <thead><tr><th>Assessor</th>@foreach ($criteria as $c)<th class="num" title="{{ $c['label'] }}">{{ \Illuminate\Support\Str::limit($c['label'], 10) }}</th>@endforeach<th class="num">Total</th><th></th></tr></thead>
                <tbody>
                @foreach ($applicant->assessments as $as)
                    <tr><td>{{ $as->assessor }}</td>@foreach ($criteria as $k => $c)<td class="num">{{ $as->scores[$k] ?? 0 }}</td>@endforeach<td class="num"><b>{{ number_format($as->total, 2) }}</b></td>
                        <td>@unless ($readOnly)<form method="post" action="{{ route('admin.recruitment.assessment.destroy', $as) }}" onsubmit="return confirm('Remove these scores?')">@csrf @method('delete')<button class="btn btn-sm" aria-label="Remove">×</button></form>@endunless</td></tr>
                @endforeach
                <tr><th>Average</th>@foreach ($criteria as $k => $c)<th class="num">{{ $scores['criteria'][$k] }}</th>@endforeach<th class="num">{{ number_format($scores['total'], 2) }}</th><th></th></tr>
                </tbody>
            </table></div>
            @endif
            @unless ($readOnly || $applicant->status === 'appointed')
            <form method="post" action="{{ route('admin.recruitment.assess', $applicant) }}" style="margin-top:12px">@csrf
                <div class="field"><label for="assessor">HRMPSB member</label><input type="text" id="assessor" name="assessor" required list="assessors" placeholder="Name of the member">
                    <datalist id="assessors">@foreach ($applicant->assessments->pluck('assessor') as $n)<option value="{{ $n }}">@endforeach</datalist></div>
                <div class="row">
                @foreach ($criteria as $k => $c)
                    <div class="field"><label for="s_{{ $k }}">{{ $c['label'] }} <span class="muted">(max {{ $c['max'] }})</span></label><input type="number" step="0.01" min="0" max="{{ $c['max'] }}" id="s_{{ $k }}" name="scores[{{ $k }}]" required></div>
                @endforeach
                </div>
                <div class="field"><label for="a_remarks">Remarks</label><input type="text" id="a_remarks" name="remarks"></div>
                <button class="btn">Save scores</button>
            </form>
            @endunless
        </section>
        @if (! $readOnly && in_array($applicant->status, ['selected', 'shortlisted', 'qualified'], true))
        <section class="card">
            <h2>Appoint</h2>
            <form method="post" action="{{ route('admin.recruitment.appoint', $applicant) }}" onsubmit="return confirm('Appoint {{ $applicant->full_name }} as {{ $v->title }}?')">@csrf
                @unless ($applicant->employee_id)<div class="field"><label for="employee_no">New employee no.</label><input type="text" id="employee_no" name="employee_no" required></div>@endunless
                <div class="row">
                    <div class="field"><label for="ap_type">Employment</label><select id="ap_type" name="employment_type">@foreach ($types as $k => $t)<option value="{{ $k }}" @selected($v->employment_type === $k)>{{ $t['label'] }}</option>@endforeach</select></div>
                    <div class="field"><label for="date_hired">Effective date</label><input type="date" id="date_hired" name="date_hired" value="{{ today()->toDateString() }}" required></div>
                </div>
                <div class="row">
                    <div class="field"><label for="step">Step</label><input type="number" id="step" name="step" min="1" max="8" value="1" required></div>
                    <div class="field"><label for="monthly_salary">Monthly salary (₱)</label><input type="number" step="0.01" id="monthly_salary" name="monthly_salary" value="{{ $v->monthly_salary }}" required></div>
                </div>
                <button class="btn btn-primary">{{ $applicant->employee_id ? 'Appoint to this position' : 'Appoint and create employee record' }}</button>
            </form>
            <p class="hint">Adds the service record line and links the plantilla item. You'll then complete the 201 file and enroll their face.</p>
        </section>
        @endif
    </div>
</div>
@endsection
