@extends('layouts.app')
@section('title', 'My 201')
@section('content')
<div class="page-head"><div><h1>My 201 file</h1><div class="muted">{{ $employee->employee_no }} · {{ $employee->type_label }}. To correct anything, contact HR.</div></div>
    <div class="actions">
        <a class="btn btn-primary" href="{{ route('me.profile.pds') }}">Update my PDS</a>
        <a class="btn" href="{{ route('print.pds', $employee) }}" target="_blank">Print PDS</a>
        <a class="btn" href="{{ route('print.service-record', $employee) }}" target="_blank">Print service record</a>
    </div></div>
<div class="grid grid-2">
    <section class="card">
        <h2>Employment</h2>
        <dl class="kv">
            <dt>Position</dt><dd>{{ $employee->position ?: $employee->plantillaPosition?->title ?: '—' }}</dd>
            <dt>Office</dt><dd>{{ $employee->office ?: '—' }}</dd>
            @if ($employee->salary_grade)<dt>Salary grade</dt><dd>SG {{ $employee->salary_grade }}{{ $employee->step ? ', Step '.$employee->step : '' }}</dd>@endif
            @if ($employee->contract_end)<dt>Contract</dt><dd>{{ $employee->contract_start?->format('M j, Y') }} to {{ $employee->contract_end->format('M j, Y') }}</dd>@endif
            <dt>Supervisor</dt><dd>{{ $employee->supervisor?->full_name ?? '—' }}</dd>
            <dt>Date hired</dt><dd>{{ $employee->date_hired?->format('M j, Y') ?? '—' }}</dd>
        </dl>
    </section>
    <section class="card">
        <h2>Eligibility & education</h2>
        @forelse ($employee->eligibilities as $e)<div>{{ $e->name }} {{ $e->rating ? '('.$e->rating.')' : '' }}</div>@empty<div class="muted">No eligibility on file.</div>@endforelse
        <hr style="border:0;border-top:1px solid var(--border)">
        @forelse ($employee->educations as $e)<div class="small">{{ \App\Models\Education::LEVELS[$e->level] }}: {{ $e->school }} {{ $e->degree ? '— '.$e->degree : '' }}</div>@empty<div class="muted small">No education on file.</div>@endforelse
    </section>
    <section class="card" style="grid-column:1/-1">
        <h2>Trainings</h2>
        <table class="table"><tbody>
        @forelse ($employee->trainings as $t)<tr><td>{{ $t->title }}</td><td class="num small">{{ $t->date_from->format('M j, Y') }}</td><td class="num">{{ $t->hours }} h</td></tr>
        @empty <tr><td class="muted">None on file.</td></tr> @endforelse
        </tbody></table>
    </section>
</div>
@endsection
