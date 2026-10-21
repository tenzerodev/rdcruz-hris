@extends('layouts.app')
@section('title', $employee->full_name)
@section('content')
@php
    $tabs = ['overview' => 'Overview', '201' => '201 File', 'service' => 'Service Record', 'documents' => 'Documents', 'leave' => 'Leave'];
    $money = fn ($v) => $v !== null ? '₱'.number_format((float) $v, 2) : '—';
@endphp
<div class="page-head">
    <div>
        <h1>{{ $employee->full_name }}</h1>
        <div class="muted">{{ $employee->employee_no }} · {{ $employee->position ?: $employee->plantillaPosition?->title }} · {{ $employee->office }}
            <span class="badge badge-info">{{ $employee->type_label }}</span>
            @unless ($employee->is_active)<span class="badge badge-error">inactive</span>@endunless
            @if ($employee->contractEndsSoon())<span class="badge badge-warn">contract ends {{ $employee->contract_end->format('M j, Y') }}</span>@endif
        </div>
    </div>
    <div class="actions">
        <a class="btn" href="{{ route('admin.employees.edit', $employee) }}">Edit</a>
        <a class="btn" href="{{ route('admin.employees.enroll', $employee) }}">Face ({{ $employee->face_samples_count }})</a>
        <a class="btn" href="{{ route('admin.dtr.print', ['month' => now()->format('Y-m'), 'employee_ids' => [$employee->id]]) }}" target="_blank">Form 48</a>
    </div>
</div>
<nav class="tabs" aria-label="Employee sections">
    @foreach ($tabs as $key => $label)
        <a href="{{ route('admin.employees.show', [$employee, 'tab' => $key]) }}" @if ($tab === $key) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
</nav>

@if ($tab === 'overview')
<div class="grid grid-2">
    <section class="card">
        <h2>Employment</h2>
        <dl class="kv">
            <dt>Type</dt><dd>{{ $employee->type_label }}</dd>
            @if ($employee->plantillaPosition)<dt>Plantilla item</dt><dd>{{ $employee->plantillaPosition->item_no }} · {{ $employee->plantillaPosition->title }}</dd>@endif
            @if ($employee->salary_grade)<dt>Salary grade / step</dt><dd>SG {{ $employee->salary_grade }}{{ $employee->step ? ', Step '.$employee->step : '' }}</dd>@endif
            @if ($employee->monthly_salary)<dt>Monthly salary</dt><dd>{{ $money($employee->monthly_salary) }}</dd>@endif
            @if ($employee->rules()['contract'])
                <dt>Contract</dt><dd>{{ $employee->contract_start?->format('M j, Y') ?? '—' }} to {{ $employee->contract_end?->format('M j, Y') ?? '—' }}</dd>
                <dt>Rate</dt><dd>{{ $money($employee->daily_rate) }}</dd>
            @endif
            @if ($employee->agency_name)<dt>{{ $employee->employment_type === 'dbp' ? 'DBP office' : 'Agency' }}</dt><dd>{{ $employee->agency_name }} {{ $employee->agency_contract_no ? '('.$employee->agency_contract_no.')' : '' }}</dd>@endif
            <dt>Date hired</dt><dd>{{ $employee->date_hired?->format('M j, Y') ?? '—' }}</dd>
            @php $unit = $employee->currentUnit(); @endphp
            <dt>Organization</dt><dd>@if ($unit){{ collect($unit->path())->map(fn ($u) => $u->name)->join(' › ') }}@else<span class="muted">Not assigned</span> · <a href="{{ route('admin.org.index') }}">assign</a>@endif</dd>
            <dt>Supervisor</dt><dd>{{ $employee->supervisor?->full_name ?? '—' }}</dd>
            <dt>Schedule</dt><dd>{{ $employee->schedule_type === 'flexi' ? 'Flexi-time' : 'Fixed hours' }} · {{ $employee->punchesOncePerDay() ? 'time in/out once a day' : 'AM and PM in/out' }}</dd>
            <dt>Entitlements</dt><dd>{{ $employee->earnsLeave() ? 'Leave credits' : 'No leave credits' }} · {{ $employee->overtimeEligible() ? 'Overtime/COC' : 'No overtime' }}</dd>
            @if ($employee->date_separated)<dt>Separated</dt><dd>{{ $employee->date_separated->format('M j, Y') }} — {{ $employee->separation_reason }}</dd>@endif
        </dl>
    </section>
    <section class="card">
        <h2>Personal</h2>
        <dl class="kv">
            <dt>Sex / birth date</dt><dd>{{ ucfirst((string) $employee->sex) ?: '—' }} · {{ $employee->birth_date?->format('M j, Y') ?? '—' }}{{ $employee->birth_date ? ' ('.$employee->birth_date->age.')' : '' }}</dd>
            <dt>Civil status</dt><dd>{{ $employee->civil_status ? ucwords(str_replace('_', ' ', $employee->civil_status)) : '—' }}</dd>
            <dt>Email / mobile</dt><dd>{{ $employee->email ?: '—' }} · {{ $employee->mobile ?: '—' }}</dd>
            <dt>Address</dt><dd>{{ $employee->address ?: '—' }}</dd>
            <dt>Emergency</dt><dd>{{ $employee->emergency_contact ?: '—' }} {{ $employee->emergency_phone }}</dd>
            <dt>TIN</dt><dd>{{ $employee->tin ?: '—' }}</dd>
            <dt>GSIS / PhilHealth</dt><dd>{{ $employee->gsis_no ?: '—' }} · {{ $employee->philhealth_no ?: '—' }}</dd>
            <dt>Pag-IBIG / SSS</dt><dd>{{ $employee->pagibig_no ?: '—' }} · {{ $employee->sss_no ?: '—' }}</dd>
            <dt>Login</dt><dd>{{ $employee->user?->username ?? 'none (kiosk only)' }}</dd>
        </dl>
        <form class="inline-form" method="get" action="{{ route('print.certificate', $employee) }}" target="_blank" style="margin-top:14px">
            <div class="field" style="flex:1;min-width:180px"><label for="purpose">Certificate of employment — purpose</label><input type="text" id="purpose" name="purpose" placeholder="e.g. loan application"></div>
            <button class="btn">Print COE</button>
        </form>
    </section>
</div>
@endif

@if ($tab === '201')
<div class="actions" style="justify-content:flex-start;margin-bottom:12px">
    <a class="btn" href="{{ route('admin.employees.pds', $employee) }}">Edit PDS (family, work experience, Q34–40…)</a>
    <a class="btn" href="{{ route('print.pds', $employee) }}" target="_blank">Print PDS (CS Form 212)</a>
    <a class="btn" href="{{ route('admin.imports.index') }}">Import 201 records (CSV)</a>
    <span class="small muted" style="align-self:center">PDS last updated {{ $employee->pds_updated_at?->format('M j, Y') ?? 'never' }}</span>
</div>
<div class="grid grid-2">
    <section class="card">
        <h2>Educational background</h2>
        <table class="table"><tbody>
        @forelse ($employee->educations->sortBy(fn ($e) => array_search($e->level, array_keys(\App\Models\Education::LEVELS))) as $e)
            <tr><td><b>{{ \App\Models\Education::LEVELS[$e->level] }}</b><div class="small">{{ $e->school }}{{ $e->degree ? ' — '.$e->degree : '' }}</div>
                <div class="small muted">{{ $e->year_from }}–{{ $e->year_to }} {{ $e->year_graduated ? '· graduated '.$e->year_graduated : '' }} {{ $e->honors }}</div></td>
                <td>@include('admin.employees._delete', ['kind' => 'education', 'id' => $e->id])</td></tr>
        @empty <tr><td class="muted">None yet.</td></tr> @endforelse
        </tbody></table>
        <details class="add"><summary>Add education</summary>
            <form method="post" action="{{ route('admin.employees.records.store', [$employee, 'education']) }}">@csrf
                <div class="row">
                    <div class="field"><label>Level</label><select name="level">@foreach (\App\Models\Education::LEVELS as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
                    <div class="field"><label>School</label><input type="text" name="school" required></div>
                </div>
                <div class="field"><label>Degree / course</label><input type="text" name="degree"></div>
                <div class="row">
                    <div class="field"><label>From</label><input type="text" name="year_from" inputmode="numeric" maxlength="4"></div>
                    <div class="field"><label>To</label><input type="text" name="year_to" inputmode="numeric" maxlength="4"></div>
                    <div class="field"><label>Graduated</label><input type="text" name="year_graduated" inputmode="numeric" maxlength="4"></div>
                </div>
                <div class="field"><label>Honors</label><input type="text" name="honors"></div>
                <button class="btn btn-primary btn-sm">Add</button>
            </form>
        </details>
    </section>
    <section class="card">
        <h2>Civil service eligibility</h2>
        <table class="table"><tbody>
        @forelse ($employee->eligibilities as $e)
            <tr><td><b>{{ $e->name }}</b> {{ $e->rating ? '· '.$e->rating : '' }}
                <div class="small muted">{{ $e->exam_date?->format('M j, Y') }} {{ $e->exam_place }} {{ $e->license_no ? '· License '.$e->license_no : '' }} {{ $e->valid_until ? '· valid until '.$e->valid_until->format('M j, Y') : '' }}</div></td>
                <td>@include('admin.employees._delete', ['kind' => 'eligibility', 'id' => $e->id])</td></tr>
        @empty <tr><td class="muted">None yet.</td></tr> @endforelse
        </tbody></table>
        <details class="add"><summary>Add eligibility</summary>
            <form method="post" action="{{ route('admin.employees.records.store', [$employee, 'eligibility']) }}">@csrf
                <div class="field"><label>Eligibility</label><input type="text" name="name" placeholder="Career Service Professional, RA 1080, …" required></div>
                <div class="row">
                    <div class="field"><label>Rating</label><input type="text" name="rating"></div>
                    <div class="field"><label>Exam date</label><input type="date" name="exam_date"></div>
                    <div class="field"><label>Place</label><input type="text" name="exam_place"></div>
                </div>
                <div class="row">
                    <div class="field"><label>License no.</label><input type="text" name="license_no"></div>
                    <div class="field"><label>Valid until</label><input type="date" name="valid_until"></div>
                </div>
                <button class="btn btn-primary btn-sm">Add</button>
            </form>
        </details>
    </section>
    <section class="card" style="grid-column:1/-1">
        <h2>Learning & development</h2>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Title</th><th>Dates</th><th>Hours</th><th>Type</th><th>Sponsor</th><th></th></tr></thead><tbody>
            @forelse ($employee->trainings as $t)
                <tr><td>{{ $t->title }}</td><td class="num small">{{ $t->date_from->format('M j, Y') }}{{ $t->date_to && $t->date_to->ne($t->date_from) ? ' – '.$t->date_to->format('M j, Y') : '' }}</td>
                    <td class="num">{{ $t->hours }}</td><td>{{ \App\Models\Training::TYPES[$t->type] ?? '' }}</td><td>{{ $t->sponsor }}</td>
                    <td>@include('admin.employees._delete', ['kind' => 'training', 'id' => $t->id])</td></tr>
            @empty <tr><td colspan="6" class="muted">None yet.</td></tr> @endforelse
            </tbody></table></div>
        <details class="add"><summary>Add training</summary>
            <form method="post" action="{{ route('admin.employees.records.store', [$employee, 'training']) }}">@csrf
                <div class="field"><label>Title</label><input type="text" name="title" required></div>
                <div class="row">
                    <div class="field"><label>From</label><input type="date" name="date_from" required></div>
                    <div class="field"><label>To</label><input type="date" name="date_to"></div>
                    <div class="field"><label>Hours</label><input type="number" name="hours" min="1"></div>
                    <div class="field"><label>Type</label><select name="type"><option value=""></option>@foreach (\App\Models\Training::TYPES as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
                </div>
                <div class="field"><label>Conducted / sponsored by</label><input type="text" name="sponsor"></div>
                <button class="btn btn-primary btn-sm">Add</button>
            </form>
        </details>
    </section>
</div>
@endif

@if ($tab === 'service')
<section class="card">
    <div class="page-head" style="margin-bottom:8px"><h2 style="margin:0">Service record</h2>
        <a class="btn btn-sm" href="{{ route('print.service-record', $employee) }}" target="_blank">Print service record</a></div>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>From</th><th>To</th><th>Designation</th><th>Status</th><th>Annual salary</th><th>Office / branch</th><th>LWOP</th><th>Separation</th><th></th></tr></thead><tbody>
        @forelse ($employee->serviceRecords as $r)
            <tr><td class="num">{{ $r->date_from->format('m/d/Y') }}</td><td class="num">{{ $r->date_to?->format('m/d/Y') ?? 'present' }}</td>
                <td>{{ $r->designation }}</td><td>{{ $r->status }}</td><td class="num">{{ $r->annual_salary ? number_format($r->annual_salary, 2) : '' }}</td>
                <td>{{ $r->office }}{{ $r->branch ? ' / '.$r->branch : '' }}</td><td>{{ $r->leave_without_pay }}</td><td>{{ $r->separation }}</td>
                <td>@include('admin.employees._delete', ['kind' => 'service', 'id' => $r->id])</td></tr>
        @empty <tr><td colspan="9" class="muted">No service record entries yet.</td></tr> @endforelse
        </tbody></table></div>
    <details class="add" open><summary>Add entry</summary>
        <form method="post" action="{{ route('admin.employees.records.store', [$employee, 'service']) }}">@csrf
            <div class="row">
                <div class="field"><label>From</label><input type="date" name="date_from" required></div>
                <div class="field"><label>To (blank = present)</label><input type="date" name="date_to"></div>
                <div class="field"><label>Designation</label><input type="text" name="designation" value="{{ $employee->position }}" required></div>
                <div class="field"><label>Status</label><input type="text" name="status" value="{{ $employee->type_label }}" required></div>
            </div>
            <div class="row">
                <div class="field"><label>Annual salary</label><input type="number" step="0.01" name="annual_salary" value="{{ $employee->monthly_salary ? $employee->monthly_salary * 12 : '' }}"></div>
                <div class="field"><label>Office</label><input type="text" name="office" value="{{ $employee->office }}"></div>
                <div class="field"><label>Branch</label><input type="text" name="branch" value="{{ \App\Models\Setting::get('agency_name') }}"></div>
            </div>
            <div class="row">
                <div class="field"><label>Leave w/o pay</label><input type="text" name="leave_without_pay"></div>
                <div class="field"><label>Separation (date / cause)</label><input type="text" name="separation"></div>
                <div class="field"><label>Remarks</label><input type="text" name="remarks"></div>
            </div>
            <button class="btn btn-primary btn-sm">Add</button>
        </form>
    </details>
</section>
@endif

@if ($tab === 'documents')
<div class="grid grid-2">
    <section class="card">
        <h2>201 documents</h2>
        <table class="table"><tbody>
        @forelse ($employee->documents as $d)
            <tr><td><a href="{{ route('print.document', $d) }}">{{ $d->title }}</a><div class="small muted">{{ \App\Models\Document::CATEGORIES[$d->category] ?? $d->category }} · {{ number_format($d->size / 1024) }} KB · {{ $d->created_at->format('M j, Y') }}</div></td>
                <td><form method="post" action="{{ route('admin.documents.destroy', $d) }}" class="actions" onsubmit="return confirm('Delete this document?')">@csrf @method('delete')<button class="btn btn-sm btn-danger">Delete</button></form></td></tr>
        @empty <tr><td class="muted">No documents yet.</td></tr> @endforelse
        </tbody></table>
    </section>
    <section class="card">
        <h2>Upload</h2>
        <form method="post" action="{{ route('admin.employees.documents.store', $employee) }}" enctype="multipart/form-data">@csrf
            <div class="field"><label for="category">Category</label><select id="category" name="category">@foreach (\App\Models\Document::CATEGORIES as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
            <div class="field"><label for="dtitle">Title</label><input type="text" id="dtitle" name="title" placeholder="e.g. Appointment — Admin Officer II, 2024" required></div>
            <div class="field"><label for="file">File (PDF, image, Word, Excel; max 20 MB)</label><input type="file" id="file" name="file" required></div>
            <button class="btn btn-primary">Upload</button>
        </form>
        <p class="hint">Files are stored privately; only HR, the employee's supervisor and the employee can open them.</p>
    </section>
</div>
@endif

@if ($tab === 'leave')
<div class="grid grid-2">
    <section class="card">
        <h2>Balances</h2>
        @if ($balances)
            <div class="today-punches">
                @foreach (config('hris.balances') as $k => $l)<div><b>{{ rtrim(rtrim(number_format($balances[$k], 3), '0'), '.') ?: '0' }}</b><span>{{ $k === 'COC' ? 'COC hours' : $l }}</span></div>@endforeach
            </div>
            <p><a class="btn btn-sm" href="{{ route('admin.employees.leave-card', $employee) }}">Open leave card</a></p>
        @elseif ($wellness = app(\App\Services\LeaveService::class)->wellnessEligible($employee) ? app(\App\Services\LeaveService::class)->wellness($employee, now()->year) : null)
            <div class="today-punches">
                <div><b>{{ $wellness['remaining'] + 0 }}</b><span>Wellness leave left ({{ now()->year }})</span></div>
                <div><b>{{ $wellness['used'] + 0 }}</b><span>Used or pending</span></div>
                <div><b>{{ $wellness['entitled'] + 0 }}</b><span>Entitled</span></div>
            </div>
            <p class="hint">Based on the date first engaged ({{ ($employee->date_hired ?? $employee->contract_start)?->format('M j, Y') ?? 'not set — counted as before this year' }}). Set "Date hired" to the first engagement, not the latest contract renewal.</p>
        @else
            <p class="muted">{{ $employee->type_label }} personnel have no leave credits. Leave filed as "Others" only excuses the days on the DTR.</p>
        @endif
    </section>
    <section class="card">
        <h2>Recent applications</h2>
        <table class="table"><tbody>
        @forelse ($leaves as $l)
            <tr><td>{{ $l->type_name }}<div class="small muted">{{ $l->date_from->format('M j') }}{{ $l->date_to->ne($l->date_from) ? '–'.$l->date_to->format('M j') : '' }}, {{ $l->date_from->format('Y') }} · {{ $l->days + 0 }} day(s)</div></td>
                <td>@include('admin.leave._status', ['status' => $l->status])</td>
                <td><a class="btn btn-sm" href="{{ route('print.leave', $l) }}" target="_blank">Form 6</a></td></tr>
        @empty <tr><td class="muted">None.</td></tr> @endforelse
        </tbody></table>
        <a href="{{ route('admin.leave.index', ['employee_id' => $employee->id, 'status' => 'all']) }}">All leave →</a>
    </section>
</div>
@endif
@endsection
