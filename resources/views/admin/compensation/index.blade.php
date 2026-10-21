@extends('layouts.app')
@section('title', 'Step increments & salary')
@section('content')
@php $money = fn ($v) => $v === null ? '—' : '₱'.number_format((float) $v, 2); @endphp
<div class="page-head">
    <div><h1>Step increments &amp; salary adjustments</h1>
        <div class="muted">One step for every 3 years of continuous satisfactory service in the present position, up to step 8. Salary adjustments come from a new salary schedule (tranche).</div></div>
</div>
@if (session('print'))<div class="alert alert-ok"><x-icon name="file" /><div>Notice ready. <a href="{{ session('print') }}" target="_blank">Print it</a>.</div></div>@endif
@if ($missingDates)<div class="alert alert-warn"><x-icon name="alert" />{{ $missingDates }} plantilla employee(s) have no step date or date hired, so their increments can't be computed. Set "Step date" on their 201 file.</div>@endif

<section class="card" style="margin-bottom:16px">
    <div class="page-head" style="margin-bottom:8px">
        <h2 style="margin:0">Step increments due</h2>
        <form class="inline-form" method="get"><div class="field"><label for="days" class="sr">Within</label><select id="days" name="days" onchange="this.form.submit()">
            @foreach ([30 => 'Due within 30 days', 60 => 'Due within 60 days', 180 => 'Due within 6 months', 365 => 'Due within a year'] as $d => $l)<option value="{{ $d }}" @selected($days === $d)>{{ $l }}</option>@endforeach
        </select></div></form>
    </div>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Employee</th><th>Since</th><th>Due</th><th>Now</th><th>New</th><th></th></tr></thead>
        <tbody>
        @forelse ($due as $r)
            @php $e = $r['employee']; @endphp
            <tr>
                <td><b>{{ $e->full_name }}</b><div class="small muted">{{ $e->position }} · {{ $e->office }}</div></td>
                <td class="small">{{ $r['base']?->format('M j, Y') }}</td>
                <td>{{ $r['due']->format('M j, Y') }}@if ($r['due']->isPast())<div><span class="badge badge-warn">Overdue</span></div>@endif</td>
                <td class="num">SG {{ $e->salary_grade }}-{{ $e->step ?: 1 }}<div class="small">{{ $money($r['current']) }}</div></td>
                <td class="num">SG {{ $e->salary_grade }}-{{ $r['new_step'] }}<div class="small"><b>{{ $money($r['new_salary']) }}</b></div></td>
                <td>
                    @if ($r['blocker'])<span class="badge badge-error">Not yet</span><div class="small muted" style="max-width:260px">{{ $r['blocker'] }}</div>
                    @elseif (! $readOnly)
                        <form method="post" action="{{ route('admin.compensation.grant', $e) }}" class="inline-form" onsubmit="return confirm('Grant step {{ $r['new_step'] }} to {{ $e->full_name }}? Salary, 201 file and service record are updated.')">
                            @csrf <input type="hidden" name="effective_date" value="{{ $r['due']->toDateString() }}">
                            <button class="btn btn-sm btn-primary">Grant &amp; issue NOSI</button>
                        </form>
                    @endif
                </td>
            </tr>
        @empty <tr><td colspan="6" class="empty">No step increments due in this window.</td></tr> @endforelse
        </tbody>
    </table></div>
    <p class="hint">Based on the 201 file's step date (or date hired), salary grade and step. An Unsatisfactory or Poor IPCR in the 3 years holds the increment back. Leave without pay of more than the allowed days extends the date — adjust the step date if needed.</p>
</section>

<div class="grid grid-2">
    <section class="card">
        <h2>Salary schedules</h2>
        <div class="table-wrap"><table class="table"><tbody>
        @forelse ($schedules as $s)
            <tr><td><a href="{{ route('admin.compensation.schedule', $s) }}"><b>{{ $s->name }}</b></a>@if ($current?->is($s)) <span class="badge badge-ok">In effect</span>@endif
                <div class="small muted">From {{ $s->effective_date->format('M j, Y') }} · {{ $s->rates_count }} amounts</div></td>
                <td><a class="btn btn-sm" href="{{ route('admin.compensation.schedule', $s) }}">Open</a></td></tr>
        @empty <tr><td class="empty">No salary schedule yet. Add the one your agency uses (e.g. the current SSL tranche or GCG-approved plan).</td></tr> @endforelse
        </tbody></table></div>
        @unless ($readOnly)
        <details class="add"><summary>Add a salary schedule</summary>
            <form method="post" action="{{ route('admin.compensation.schedules.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="field"><label for="name">Name</label><input type="text" id="name" name="name" required placeholder="e.g. SSL VI Second Tranche"></div>
                <div class="row">
                    <div class="field"><label for="effective_date">Effective</label><input type="date" id="effective_date" name="effective_date" required></div>
                    <div class="field"><label for="basis">Legal basis</label><input type="text" id="basis" name="basis" placeholder="e.g. R.A. No. 11966; DBM LBC No. …"></div>
                </div>
                <div class="field"><label for="file">Amounts (CSV: grade, step1 … step8)</label><input type="file" id="file" name="file" accept=".csv,text/csv">
                    <div class="hint"><a href="{{ route('admin.compensation.template') }}">Download the template</a>. Or copy an existing schedule and edit it, or leave both blank and type the amounts in.</div></div>
                @if ($schedules->isNotEmpty())
                    <div class="field"><label for="copy_from">…or copy amounts from</label><select id="copy_from" name="copy_from"><option value="">—</option>@foreach ($schedules as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach</select></div>
                @endif
                <button class="btn btn-primary">Save</button>
            </form>
        </details>
        @endunless
    </section>
    <section class="card">
        <div class="card-head"><h2>Recent notices</h2></div>
        <div class="table-wrap"><table class="table"><tbody>
        @forelse ($notices as $n)
            <tr><td><b>{{ $n->employee->full_name }}</b><div class="small muted">{{ strtoupper($n->type) }} · effective {{ $n->effective_date->format('M j, Y') }}</div></td>
                <td class="num small">{{ $money($n->old_salary) }} → <b>{{ $money($n->new_salary) }}</b></td>
                <td><a class="btn btn-sm" href="{{ route('admin.compensation.notice', $n) }}" target="_blank">Print</a></td></tr>
        @empty <tr><td class="empty">None yet.</td></tr> @endforelse
        </tbody></table></div>
    </section>
</div>
@endsection
