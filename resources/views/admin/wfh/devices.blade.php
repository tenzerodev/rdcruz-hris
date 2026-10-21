@extends('layouts.app')
@section('title', 'Issued computers')
@section('content')
<div class="page-head">
    <div><h1>Issued computers</h1><div class="muted">Agency laptops and desktops that employees may time in from on work-from-home days. Each is tied to one employee.</div></div>
    <a class="btn" href="{{ route('admin.wfh.index') }}"><x-icon name="laptop" size="16" /> Work from home</a>
</div>

@if ($c = session('code'))
    <div class="alert alert-ok" role="status"><x-icon name="check" /><div>
        <b>Activation code for {{ $c['device'] }} ({{ $c['employee'] }}):</b>
        <span style="font:700 1.5rem/1.2 ui-monospace,monospace;letter-spacing:.12em;display:block;margin:6px 0">{{ $c['code'] }}</span>
        On that computer, the employee signs in to the HRIS, opens <b>My devices</b> and types this code. It works once and expires in 3 days. It is not shown again.
    </div></div>
@endif

<div class="grid grid-2" style="margin-bottom:16px">
    <section class="card">
        <h2>Register a computer</h2>
        <form method="post" action="{{ route('admin.devices.store') }}">
            @csrf
            <div class="field"><label for="employee_id">Issued to</label><select id="employee_id" name="employee_id" required><option value="">— Choose —</option>
                @foreach ($employees as $e)<option value="{{ $e->id }}" @selected(old('employee_id') == $e->id)>{{ $e->full_name }} — {{ $e->office }}</option>@endforeach</select></div>
            <div class="field"><label for="name">Computer</label><input type="text" id="name" name="name" value="{{ old('name') }}" required placeholder="e.g. Dell Latitude 5440"></div>
            <div class="row">
                <div class="field"><label for="property_no">Property no.</label><input type="text" id="property_no" name="property_no" value="{{ old('property_no') }}" placeholder="PAR / ICS no."></div>
                <div class="field"><label for="serial_no">Serial no.</label><input type="text" id="serial_no" name="serial_no" value="{{ old('serial_no') }}"></div>
            </div>
            <label class="check"><input type="checkbox" name="restrict_login" value="1" @checked(old('restrict_login'))>
                <span>Also allow this employee to sign in only from issued computers<span class="hint" style="display:block">They can't use the HRIS from a phone or other computer. The first sign-in on this computer asks for the code.</span></span></label>
            <button class="btn btn-primary">Register and get code</button>
        </form>
    </section>
    <section class="card">
        <h2>How it works</h2>
        <ol class="steps">
            <li>Register the computer here and note the activation code.</li>
            <li>On that computer, the employee signs in, opens <b>My devices</b> and types the code. The browser is now remembered as that computer.</li>
            <li>On work-from-home days, time-ins are accepted only from that browser (plus face recognition), when the arrangement requires an issued computer.</li>
            <li>Check <b>Registered on</b>: it shows the browser and internet address where the code was entered. If it doesn't match the issued computer (for example a phone, or a Mac when you issued a Windows laptop), make a new code and enter it on the right computer yourself.</li>
            <li>If the computer is returned, lost or re-imaged, <b>disable</b> it, or make a new code to register it again.</li>
            <li>Optional: limit an employee's <b>sign-in</b> to their issued computers. If the computer isn't registered yet, the sign-in page asks for the code.</li>
        </ol>
        <p class="hint">Use one browser on the computer (e.g. Chrome). Clearing that browser's cookies removes the registration; make a new code if that happens.</p>
    </section>
</div>

<section class="card" style="margin-bottom:16px">
    <div class="card-head"><h2>Sign in only from issued computers ({{ $restricted->count() }})</h2></div>
    <div class="table-wrap"><table class="table"><tbody>
    @forelse ($restricted as $e)
        <tr><td><b>{{ $e->full_name }}</b><div class="small muted">{{ $e->office }}</div></td>
            <td class="small">{!! $e->issuedDevices->isEmpty() ? '<span class="badge badge-warn">No issued computer — they cannot sign in</span>' : e($e->issuedDevices->pluck('name')->join(', ')) !!}</td>
            <td><form method="post" action="{{ route('admin.devices.restrict', $e) }}">@csrf<input type="hidden" name="on" value="0"><button class="btn btn-sm">Allow any device</button></form></td></tr>
    @empty <tr><td class="empty">Everyone may sign in from any device.</td></tr> @endforelse
    </tbody></table></div>
    <form method="post" class="inline-form" style="margin-top:12px" onsubmit="this.action = this.dataset.base.replace('__ID__', this.employee.value)" data-base="{{ route('admin.devices.restrict', '__ID__') }}">
        @csrf <input type="hidden" name="on" value="1">
        <div class="field"><label for="r-emp">Limit sign-in for</label><select id="r-emp" name="employee" required><option value="">— Choose employee —</option>
            @foreach ($employees->where('login_device_only', false) as $e)<option value="{{ $e->id }}">{{ $e->full_name }}</option>@endforeach</select></div>
        <button class="btn">Limit to issued computers</button>
    </form>
</section>

<section class="card">
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Computer</th><th>Issued to</th><th>Status</th><th>Registered on</th><th>Last used</th><th></th></tr></thead>
        <tbody>
        @forelse ($devices as $d)
            <tr>
                <td><b>{{ $d->name }}</b><div class="small muted">{{ collect([$d->property_no ? 'Property '.$d->property_no : null, $d->serial_no ? 'S/N '.$d->serial_no : null])->filter()->join(' · ') }}</div></td>
                <td>{{ $d->employee->full_name }}</td>
                <td>@switch($d->status)
                        @case('active')<span class="badge badge-ok">Registered</span><div class="small muted">{{ $d->activated_at->format('M j, Y') }}</div>@break
                        @case('waiting')<span class="badge badge-warn">Waiting for code</span><div class="small muted">expires {{ $d->activation_expires_at->diffForHumans() }}</div>@break
                        @case('expired')<span class="badge">Code expired</span>@break
                        @default<span class="badge badge-error">Disabled</span>
                    @endswitch</td>
                <td class="small">@if ($d->activated_at && $d->browser_label)
                        {{ $d->browser_label }}
                        @if ($d->registered_on_mobile)<div><span class="badge badge-warn">Phone or tablet, not a computer</span></div>@endif
                        <div class="muted">{{ $d->activated_ip ? 'IP '.$d->activated_ip : 'IP not recorded' }}</div>
                    @else — @endif</td>
                <td class="small">{{ $d->last_used_at?->diffForHumans() ?? '—' }}@if ($d->last_used_at && $d->last_ip)<div class="muted">IP {{ $d->last_ip }}</div>@endif</td>
                <td><div class="actions">
                    @if ($d->is_active)<form method="post" action="{{ route('admin.devices.code', $d) }}" onsubmit="return confirm('Make a new code? The current registration of this computer stops working until the code is entered.')">@csrf<button class="btn btn-sm">New code</button></form>@endif
                    <form method="post" action="{{ route('admin.devices.toggle', $d) }}">@csrf<button class="btn btn-sm {{ $d->is_active ? 'btn-danger' : '' }}">{{ $d->is_active ? 'Disable' : 'Enable' }}</button></form>
                    <form method="post" action="{{ route('admin.devices.destroy', $d) }}" onsubmit="return confirm('Remove this computer? Past punches keep their record.')">@csrf @method('delete')<button class="btn btn-sm">Remove</button></form>
                </div></td>
            </tr>
        @empty <tr><td colspan="6" class="empty">No computers registered yet.</td></tr> @endforelse
        </tbody>
    </table></div>
    <div class="pagination">{{ $devices->links() }}</div>
</section>
@endsection
