@extends('layouts.app')
@section('title', $vacancy->title)
@section('content')
<div class="page-head">
    <div><h1>{{ $vacancy->title }}</h1><div class="muted">@include('admin.recruitment._status', ['status' => $vacancy->status, 'label' => \App\Models\Vacancy::STATUSES[$vacancy->status]])
        {{ $vacancy->item_no ? 'Item '.$vacancy->item_no.' · ' : '' }}{{ $vacancy->type_label }}{{ $vacancy->salary_grade ? ' · SG '.$vacancy->salary_grade : '' }} · {{ $vacancy->slots }} slot(s)
        · {{ $vacancy->posted_on?->format('M j') ?? '—' }} – {{ $vacancy->closing_on?->format('M j, Y') ?? 'open' }}</div></div>
    <div class="actions">
        @if ($vacancy->public && $vacancy->status !== 'draft')<a class="btn" href="{{ route('careers.show', $vacancy) }}" target="_blank">Public page</a>@endif
        <a class="btn" href="{{ route('admin.recruitment.comparative', $vacancy) }}" target="_blank">Comparative assessment</a>
        <a class="btn" href="{{ route('admin.recruitment.publication', ['ids' => [$vacancy->id]]) }}" target="_blank">CS Form 9</a>
        @unless ($readOnly)<a class="btn" href="{{ route('admin.recruitment.edit', $vacancy) }}">Edit</a>@endunless
    </div>
</div>
@unless ($readOnly)
<div class="actions" style="justify-content:flex-start;margin-bottom:16px">
    @foreach (['draft' => ['open' => 'Open & publish', 'cancelled' => 'Cancel'], 'open' => ['closed' => 'Close applications', 'cancelled' => 'Cancel'], 'closed' => ['open' => 'Reopen', 'cancelled' => 'Cancel'], 'cancelled' => ['draft' => 'Back to draft']][$vacancy->status] ?? [] as $to => $label)
        <form method="post" action="{{ route('admin.recruitment.status', $vacancy) }}">@csrf<input type="hidden" name="status" value="{{ $to }}"><button @class(['btn', 'btn-primary' => $to === 'open', 'btn-danger' => $to === 'cancelled']) @if ($to === 'cancelled') onclick="return confirm('Cancel this vacancy?')" @endif>{{ $label }}</button></form>
    @endforeach
    @if (in_array($vacancy->status, ['filled', 'cancelled'], true))
        <form method="post" action="{{ route('admin.recruitment.purge', $vacancy) }}" onsubmit="return confirm('Delete the uploaded files of applicants who were not appointed? This cannot be undone.')">@csrf<button class="btn">Delete applicants' files</button></form>
    @endif
</div>
@endunless
<div class="grid" style="grid-template-columns: minmax(0, 2fr) minmax(260px, 1fr)">
    <section class="card">
        <h2>Applicants ({{ $applicants->count() }})</h2>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>#</th><th>Applicant</th><th>Status</th><th class="num">Score</th><th></th></tr></thead>
            <tbody>
            @forelse ($applicants as $i => $a)
                @php $sc = $a->averageScores(); @endphp
                <tr>
                    <td class="small muted">{{ $sc['total'] !== null ? $i + 1 : '' }}</td>
                    <td><a href="{{ route('admin.recruitment.applicant', $a) }}"><b>{{ $a->full_name }}</b></a>@if ($a->employee_id) <span class="badge badge-info">Internal</span>@endif
                        <div class="small muted">{{ $a->reference_no }} · {{ $a->created_at->format('M j') }}{{ $a->current_position ? ' · '.$a->current_position : '' }}</div></td>
                    <td>@include('admin.recruitment._status', ['status' => $a->status, 'label' => $a->status_label])</td>
                    <td class="num">{{ $sc['total'] !== null ? number_format($sc['total'], 2) : '—' }}@if ($sc['assessors'])<div class="small muted">{{ $sc['assessors'] }} assessor(s)</div>@endif</td>
                    <td><a class="btn btn-sm" href="{{ route('admin.recruitment.applicant', $a) }}">Open</a></td>
                </tr>
            @empty <tr><td colspan="5" class="empty">No applicants yet.</td></tr> @endforelse
            </tbody>
        </table></div>
        <p class="hint">Steps: mark who meet the qualification standards, shortlist, enter each HRMPSB member's scores, print the comparative assessment for the appointing authority, then mark the selected applicant and appoint.</p>
    </section>
    <section class="card">
        <h2>Qualification standards</h2>
        <dl class="kv small">
            @foreach (['education' => 'Education', 'training' => 'Training', 'experience' => 'Experience', 'eligibility' => 'Eligibility'] as $f => $l)<dt>{{ $l }}</dt><dd>{!! nl2br(e($vacancy->$f ?: '—')) !!}</dd>@endforeach
        </dl>
        @unless ($readOnly)
        <details style="margin-top:16px"><summary class="btn btn-sm">Add an application received on paper</summary>
            <form method="post" action="{{ route('admin.recruitment.applicants.store', $vacancy) }}" style="margin-top:12px">@csrf
                <div class="row"><div class="field"><label for="a_last">Last name</label><input type="text" id="a_last" name="last_name" required></div><div class="field"><label for="a_first">First name</label><input type="text" id="a_first" name="first_name" required></div></div>
                <div class="field"><label for="a_email">Email</label><input type="email" id="a_email" name="email" required></div>
                <div class="field"><label for="a_emp">Current employee? (internal applicant)</label><select id="a_emp" name="employee_id"><option value="">No</option>@foreach (\App\Models\Employee::where('is_active', true)->orderBy('last_name')->get() as $e)<option value="{{ $e->id }}">{{ $e->full_name }}</option>@endforeach</select></div>
                <div class="field"><label for="a_pos">Current position</label><input type="text" id="a_pos" name="current_position"></div>
                <button class="btn btn-primary btn-sm">Add applicant</button>
            </form>
        </details>
        @endunless
    </section>
</div>
@endsection
