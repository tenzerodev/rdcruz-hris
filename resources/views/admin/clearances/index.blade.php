@extends('layouts.app')
@section('title', 'Clearance & exit')
@section('content')
<div class="page-head">
    <div><h1>Clearance &amp; exit</h1><div class="muted">Each office signs off; when all have, the employee is separated: 201 record and service record closed, login and issued computers disabled.</div></div>
</div>
<div class="grid grid-2" style="margin-bottom:16px">
    <section class="card">
        <h2>Start a clearance</h2>
        @unless ($readOnly)
        <form method="post" action="{{ route('admin.clearances.store') }}">
            @csrf
            <div class="field"><label for="employee_id">Employee</label><select id="employee_id" name="employee_id" required><option value="">— Choose —</option>
                @foreach ($employees as $e)<option value="{{ $e->id }}" @selected(old('employee_id') == $e->id)>{{ $e->full_name }} — {{ $e->office }}</option>@endforeach</select></div>
            <div class="row">
                <div class="field"><label for="separation_date">Last day / separation date</label><input type="date" id="separation_date" name="separation_date" value="{{ old('separation_date') }}" required></div>
                <div class="field"><label for="reason">Reason</label><input type="text" id="reason" name="reason" list="reasons" value="{{ old('reason') }}" required>
                    <datalist id="reasons">@foreach (config('hris.separation_reasons') as $r)<option value="{{ $r }}">@endforeach</datalist></div>
            </div>
            <button class="btn btn-primary">Start clearance</button>
        </form>
        @endunless
    </section>
    <section class="card">
        <h2>Offices that sign</h2>
        <ol class="steps">@foreach (config('hris.clearance_offices') as $o)<li><b>{{ $o['office'] }}</b> — <span class="small">{{ $o['checks'] }}</span></li>@endforeach</ol>
        <p class="hint">Change the list in config/hris.php (clearance_offices).</p>
    </section>
</div>
<section class="card">
    <div class="page-head" style="margin-bottom:8px">
        <h2 style="margin:0">Clearances</h2>
        <form class="inline-form" method="get"><div class="field"><label for="status" class="sr">Status</label><select id="status" name="status" onchange="this.form.submit()">
            @foreach (['open' => 'Open', 'completed' => 'Completed', 'cancelled' => 'Cancelled', 'all' => 'All'] as $k => $l)<option value="{{ $k }}" @selected($status === $k)>{{ $l }}</option>@endforeach</select></div></form>
    </div>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Employee</th><th>Separation</th><th>Progress</th><th>Status</th><th></th></tr></thead><tbody>
        @forelse ($clearances as $c)
            @php $done = $c->items->whereNotNull('cleared_at')->count(); @endphp
            <tr><td><b>{{ $c->employee->full_name }}</b><div class="small muted">{{ $c->employee->office }}</div></td>
                <td>{{ $c->separation_date->format('M j, Y') }}<div class="small muted">{{ $c->reason }}</div></td>
                <td>{{ $done }} of {{ $c->items->count() }} offices</td>
                <td><span class="badge {{ ['open' => 'badge-warn', 'completed' => 'badge-ok', 'cancelled' => ''][$c->status] }}">{{ ucfirst($c->status) }}</span></td>
                <td><a class="btn btn-sm" href="{{ route('admin.clearances.show', $c) }}">Open</a></td></tr>
        @empty <tr><td colspan="5" class="empty">None.</td></tr> @endforelse
        </tbody></table></div>
    <div class="pagination">{{ $clearances->links() }}</div>
</section>
@endsection
