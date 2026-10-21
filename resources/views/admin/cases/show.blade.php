@extends('layouts.app')
@section('title', $case->case_no)
@section('content')
<div class="page-head">
    <div><h1>{{ $case->case_no }} · {{ $case->offense }}</h1>
        <div class="muted">Respondent: <a href="{{ route('admin.employees.show', $case->employee) }}">{{ $case->employee->full_name }}</a> ({{ $case->employee->position }}, {{ $case->employee->office }}) · filed {{ $case->date_filed->format('M j, Y') }}
            · <span @class(['badge', 'badge-warn' => $case->isOpen(), 'badge-ok' => ! $case->isOpen()])>{{ $case->status_label }}</span></div></div>
    <div class="actions"><a class="btn" href="{{ route('admin.cases.print', $case) }}" target="_blank">Print case record</a><a class="btn" href="{{ route('admin.cases.index') }}">All cases</a></div>
</div>
@if ($others->isNotEmpty())<div class="alert alert-warn"><x-icon name="alert" /><div>Other cases of this employee: @foreach ($others as $o)<a href="{{ route('admin.cases.show', $o) }}">{{ $o->case_no }}</a> ({{ $o->offense }}, {{ $o->status_label }})@if (! $loop->last), @endif @endforeach</div></div>@endif
<div class="grid" style="grid-template-columns: minmax(0, 3fr) minmax(280px, 2fr)">
    <div class="stack">
        <section class="card">
            <h2>Record of proceedings</h2>
            <div class="table-wrap"><table class="table"><tbody>
            @foreach ($case->events as $e)
                <tr><td class="small" style="white-space:nowrap">{{ $e->date->format('M j, Y') }}</td>
                    <td><b>{{ $e->type_name }}</b>@if ($e->due_on) <span @class(['badge', 'badge-warn' => $e->due_on->isFuture() || $e->due_on->isToday(), 'badge-error' => $e->due_on->isPast() && ! $e->due_on->isToday()])>due {{ $e->due_on->format('M j') }}</span>@endif
                        @if ($e->details)<div class="small">{!! nl2br(e($e->details)) !!}</div>@endif
                        @if ($e->document_path)<div class="small"><a href="{{ route('admin.cases.events.document', $e) }}" target="_blank"><x-icon name="file" size="14" /> {{ $e->document_name }}</a></div>@endif</td>
                    <td>@unless ($readOnly)<form method="post" action="{{ route('admin.cases.events.destroy', $e) }}" onsubmit="return confirm('Remove this entry?')">@csrf @method('delete')<button class="btn btn-sm" aria-label="Remove">×</button></form>@endunless</td></tr>
            @endforeach
            </tbody></table></div>
            @unless ($readOnly)
            <form method="post" action="{{ route('admin.cases.events.store', $case) }}" enctype="multipart/form-data" style="margin-top:12px">@csrf
                <div class="row">
                    <div class="field"><label for="ev_date">Date</label><input type="date" id="ev_date" name="date" value="{{ today()->toDateString() }}" required></div>
                    <div class="field"><label for="ev_type">Step</label><select id="ev_type" name="type">@foreach (\App\Models\CaseEvent::TYPES as $k => $l)@continue(in_array($k, ['complaint', 'decision'], true))<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
                    <div class="field"><label for="ev_due">Reply / next step due</label><input type="date" id="ev_due" name="due_on"></div>
                </div>
                <div class="field"><label for="ev_details">Details</label><textarea id="ev_details" name="details" rows="2"></textarea></div>
                <div class="field"><label for="ev_doc">Document (PDF, image or Word, up to 15 MB)</label><input type="file" id="ev_doc" name="document"></div>
                <button class="btn">Add to record</button>
            </form>
            <p class="hint">Respondents are generally given 5 days to comment on a complaint and to answer a formal charge (2017 RACCS). Set the due date to get a reminder on the cases page.</p>
            @endunless
        </section>
        @if ($case->decided_on)
        <section class="card"><h2>Decision ({{ $case->decided_on->format('M j, Y') }})</h2><p><b>{{ \App\Models\AdminCase::PENALTIES[$case->penalty] ?? '' }}</b></p><p>{!! nl2br(e($case->decision)) !!}</p></section>
        @endif
    </div>
    <div class="stack">
        @unless ($readOnly)
        <section class="card">
            <h2>Case details</h2>
            <form method="post" action="{{ route('admin.cases.update', $case) }}">@csrf @method('put')
                <div class="field"><label for="c_status">Status</label><select id="c_status" name="status">@foreach (\App\Models\AdminCase::STATUSES as $k => $l)<option value="{{ $k }}" @selected($case->status === $k)>{{ $l }}</option>@endforeach</select></div>
                <div class="field"><label for="c_off">Offense</label><input type="text" id="c_off" name="offense" value="{{ $case->offense }}" required list="offenses">@include('admin.cases._offenses')</div>
                <div class="field"><label for="c_class">Classification</label><select id="c_class" name="classification"><option value="">Not yet determined</option>@foreach (\App\Models\AdminCase::CLASSIFICATIONS as $k => $l)<option value="{{ $k }}" @selected($case->classification === $k)>{{ $l }}</option>@endforeach</select></div>
                <div class="field"><label for="c_comp">Complainant</label><input type="text" id="c_comp" name="complainant" value="{{ $case->complainant }}"></div>
                <div class="field"><label for="c_desc">Summary</label><textarea id="c_desc" name="description" rows="3">{{ $case->description }}</textarea></div>
                <button class="btn">Save</button>
            </form>
        </section>
        @endunless
        <section class="card">
            <h2>Suspensions</h2>
            @forelse ($case->suspensions as $s)
                <div class="row" style="justify-content:space-between;align-items:center;margin-bottom:8px">
                    <div><b>{{ \App\Models\CaseSuspension::KINDS[$s->kind] }}</b><div class="small muted">{{ $s->date_from->format('M j, Y') }} – {{ $s->date_to->format('M j, Y') }} · {{ $s->with_pay ? 'with pay' : 'without pay' }}</div></div>
                    @unless ($readOnly)<div class="actions">
                        <form method="post" action="{{ route('admin.cases.suspensions.update', $s) }}">@csrf @method('put')<input type="hidden" name="with_pay" value="{{ $s->with_pay ? 0 : 1 }}"><button class="btn btn-sm">{{ $s->with_pay ? 'Mark without pay' : 'Mark with pay' }}</button></form>
                        <form method="post" action="{{ route('admin.cases.suspensions.destroy', $s) }}" onsubmit="return confirm('Remove?')">@csrf @method('delete')<button class="btn btn-sm">×</button></form>
                    </div>@endunless
                </div>
            @empty <p class="muted small">None.</p> @endforelse
            @unless ($readOnly)
            <details><summary class="btn btn-sm">Add preventive suspension</summary>
                <form method="post" action="{{ route('admin.cases.suspensions.store', $case) }}" style="margin-top:8px">@csrf<input type="hidden" name="kind" value="preventive">
                    <div class="row"><div class="field"><label for="ps_from">From</label><input type="date" id="ps_from" name="date_from" required></div><div class="field"><label for="ps_to">To</label><input type="date" id="ps_to" name="date_to" required></div></div>
                    <button class="btn btn-sm btn-primary">Add</button></form></details>
            @endunless
            <p class="hint">Suspended days show as SUSPENDED on the DTR (not absent) and are deducted from pay unless marked with pay.</p>
        </section>
        @if (! $readOnly && ! $case->decided_on && $case->isOpen())
        <section class="card">
            <h2>Record the decision</h2>
            <form method="post" action="{{ route('admin.cases.decide', $case) }}">@csrf
                <div class="row">
                    <div class="field"><label for="penalty">Result</label><select id="penalty" name="penalty">@foreach (\App\Models\AdminCase::PENALTIES as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
                    <div class="field"><label for="decided_on">Date of decision</label><input type="date" id="decided_on" name="decided_on" value="{{ today()->toDateString() }}" required></div>
                </div>
                <div class="row"><div class="field"><label for="s_from">Suspension from</label><input type="date" id="s_from" name="suspension_from"></div><div class="field"><label for="s_to">to</label><input type="date" id="s_to" name="suspension_to"></div></div>
                <div class="field"><label for="decision">Dispositive portion / summary</label><textarea id="decision" name="decision" rows="3" required></textarea></div>
                <button class="btn btn-primary" onclick="return confirm('Record this decision?')">Record decision</button>
            </form>
        </section>
        @endif
    </div>
</div>
@endsection
