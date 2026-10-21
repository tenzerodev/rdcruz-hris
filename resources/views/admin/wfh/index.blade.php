@extends('layouts.app')
@section('title', 'Work from home')
@section('content')
<div class="page-head">
    <div><h1>Work from home</h1><div class="muted">Set who may work from home and on which days. On those days they time in from home (no office location check) and write a daily accomplishment report.</div></div>
    <div class="actions"><a class="btn" href="{{ route('admin.devices.index') }}"><x-icon name="monitor" size="16" /> Issued computers</a><x-import-button key="wfh" /></div>
</div>

<div class="grid grid-2" style="margin-bottom:16px">
    <section class="card">
        <h2>Set a work-from-home arrangement</h2>
        <form method="post" action="{{ route('admin.wfh.store') }}">
            @csrf
            <div class="field"><label for="employee_ids">Employees</label>
                <select id="employee_ids" name="employee_ids[]" multiple size="6" required>
                    @foreach ($employees as $e)<option value="{{ $e->id }}" @selected(in_array($e->id, old('employee_ids', [])))>{{ $e->full_name }} — {{ $e->office }}</option>@endforeach
                </select><div class="hint">Hold Ctrl/⌘ to pick several.</div></div>
            <div class="row">
                <div class="field"><label for="date_from">From</label><input type="date" id="date_from" name="date_from" value="{{ old('date_from') }}" required></div>
                <div class="field"><label for="date_to">To</label><input type="date" id="date_to" name="date_to" value="{{ old('date_to') }}" required></div>
            </div>
            <fieldset class="field" style="border:0;padding:0;margin:0 0 12px">
                <legend style="font-weight:600;font-size:.88rem;margin-bottom:4px">Work-from-home days</legend>
                <div style="display:flex;gap:12px;flex-wrap:wrap">
                    @foreach (\App\Models\WfhArrangement::DAYS as $n => $d)
                        <label class="check"><input type="checkbox" name="weekdays[]" value="{{ $n }}" @checked(in_array($n, old('weekdays', [])))> {{ $d }}</label>
                    @endforeach
                </div>
                <div class="hint">Leave all unchecked for every work day in the period (e.g. full WFH). Tick days for a rotation, e.g. Mon and Wed.</div>
            </fieldset>
            <label class="check"><input type="checkbox" name="require_device" value="1" @checked(old('require_device', true))>
                <span>Time in only from their agency-issued computer<span class="hint" style="display:block">Register the computer under Issued computers first. Untick to allow any of the employee's devices.</span></span></label>
            <label class="check"><input type="checkbox" name="restrict_login" value="1" @checked(old('restrict_login'))>
                <span>Also allow them to sign in only from issued computers<span class="hint" style="display:block">Applies on all days until turned off under Issued computers.</span></span></label>
            <div class="row">
                <div class="field"><label for="reference_no">Office order / memo no.</label><input type="text" id="reference_no" name="reference_no" value="{{ old('reference_no') }}"></div>
                <div class="field"><label for="notes">Notes</label><input type="text" id="notes" name="notes" value="{{ old('notes') }}" placeholder="e.g. Flexible work arrangement, rotation A"></div>
            </div>
            <button class="btn btn-primary">Save arrangement</button>
        </form>
    </section>
    <section class="card">
        <div class="card-head"><h2>Working from home today ({{ $today->count() }})</h2></div>
        <div class="table-wrap"><table class="table"><tbody>
        @forelse ($today as $a)
            @php $dev = $devicesByEmployee[$a->employee_id] ?? collect(); @endphp
            <tr><td><b>{{ $a->employee->full_name }}</b><div class="small muted">{{ $a->employee->office }}</div></td>
                <td class="small">@if ($a->require_device){!! $dev->isNotEmpty() ? '<span class="badge badge-ok">Computer registered</span>' : '<span class="badge badge-warn">No registered computer</span>' !!}@else<span class="badge">Any device</span>@endif</td>
                <td><a class="btn btn-sm" href="{{ route('print.wfh', ['employee' => $a->employee_id, 'month' => now()->format('Y-m')]) }}" target="_blank">Report</a></td></tr>
        @empty <tr><td class="empty">No one is working from home today.</td></tr> @endforelse
        </tbody></table></div>
        <h2 style="margin-top:16px">Latest accomplishment reports</h2>
        <div class="table-wrap"><table class="table"><tbody>
        @forelse ($recentReports as $r)
            <tr><td class="small" style="white-space:nowrap">{{ $r->work_date->format('M j') }}</td><td><b>{{ $r->employee->full_name }}</b><div class="small muted">{{ \Illuminate\Support\Str::limit($r->accomplishments, 120) }}</div></td></tr>
        @empty <tr><td class="empty">None yet.</td></tr> @endforelse
        </tbody></table></div>
    </section>
</div>

<section class="card">
    <div class="page-head" style="margin-bottom:8px">
        <h2 style="margin:0">Arrangements</h2>
        <form class="inline-form" method="get">
            <div class="field"><label for="f-emp" class="sr">Employee</label><select id="f-emp" name="employee_id"><option value="">All employees</option>@foreach ($employees as $e)<option value="{{ $e->id }}" @selected(request('employee_id') == $e->id)>{{ $e->full_name }}</option>@endforeach</select></div>
            <div class="field"><label for="f-show" class="sr">Show</label><select id="f-show" name="show"><option value="current" @selected($show === 'current')>Current and upcoming</option><option value="all" @selected($show === 'all')>All, including past</option></select></div>
            <button class="btn btn-sm">Show</button>
        </form>
    </div>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Employee</th><th>Period</th><th>Days</th><th>Computer</th><th>Reference</th><th>Set by</th><th></th></tr></thead>
        <tbody>
        @forelse ($arrangements as $a)
            <tr>
                <td><b>{{ $a->employee->full_name }}</b><div class="small muted">{{ $a->employee->office }}</div></td>
                <td class="num">{{ $a->date_from->format('M j') }} – {{ $a->date_to->format('M j, Y') }}</td>
                <td>{{ $a->days_label }}</td>
                <td>{{ $a->require_device ? 'Issued only' : 'Any device' }}</td>
                <td>{{ $a->reference_no }}@if ($a->notes)<div class="small muted">{{ $a->notes }}</div>@endif</td>
                <td class="small">{{ $a->creator?->name }}</td>
                <td><div class="actions">
                    <a class="btn btn-sm" href="{{ route('print.wfh', ['employee' => $a->employee_id, 'month' => ($a->date_from->isFuture() ? $a->date_from : now())->format('Y-m')]) }}" target="_blank">Report</a>
                    @if ($a->date_to->gte(today()))
                        <form method="post" action="{{ route('admin.wfh.end', $a) }}" onsubmit="return confirm('{{ $a->date_from->lt(today()) ? 'End this arrangement as of yesterday?' : 'Cancel this arrangement?' }}')">@csrf<button class="btn btn-sm btn-danger">{{ $a->date_from->lt(today()) ? 'End' : 'Cancel' }}</button></form>
                    @endif
                </div></td>
            </tr>
        @empty <tr><td colspan="7" class="empty">No arrangements.</td></tr> @endforelse
        </tbody>
    </table></div>
    <div class="pagination">{{ $arrangements->links() }}</div>
</section>
@endsection
