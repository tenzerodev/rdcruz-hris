@extends('layouts.app')
@section('title', 'My devices')
@section('content')
<div class="page-head">
    <div><h1>My devices</h1><div class="muted">Agency-issued computers you may time in from on work-from-home days.</div></div>
</div>
<div class="grid grid-2">
    <section class="card">
        <h2>This computer</h2>
        @if ($here && $here->employee_id === $e->id)
            <p><span class="badge badge-ok">Registered</span> as <b>{{ $here->name }}</b>{{ $here->property_no ? ' · '.$here->property_no : '' }}</p>
            <p class="small muted">You can time in from this browser on work-from-home days. Keep using this same browser; clearing its cookies removes the registration.</p>
            @if ($e->login_device_only)<p class="small"><span class="badge badge-info">Sign-in limited</span> Your account can only be used on your issued computer.</p>@endif
            <form method="post" action="{{ route('me.devices.forget') }}" onsubmit="return confirm('{{ $e->login_device_only ? 'Unregister this browser? You will be signed out and need a new code from HR to sign in here again.' : 'Unregister this browser? You will need a new code from HR.' }}')">@csrf<button class="btn btn-sm">Unregister this browser</button></form>
        @elseif ($here)
            <p class="alert alert-warn"><x-icon name="alert" />This browser is registered to another employee's computer. Use your own issued computer.</p>
        @else
            <p class="small">Is this your agency-issued computer? Type the activation code HR gave you.</p>
            <form method="post" action="{{ route('me.devices.register') }}" class="inline-form">
                @csrf
                <div class="field"><label for="code">Activation code</label><input type="text" id="code" name="code" autocomplete="off" placeholder="ABCD-2345" required style="text-transform:uppercase;letter-spacing:.1em;width:160px"></div>
                <button class="btn btn-primary">Register this computer</button>
            </form>
            <p class="hint">No code? Ask HR to register your computer under Issued computers.</p>
        @endif
    </section>
    <section class="card">
        <h2>Computers issued to you</h2>
        <div class="table-wrap"><table class="table"><tbody>
        @forelse ($devices as $d)
            <tr><td><b>{{ $d->name }}</b><div class="small muted">{{ $d->property_no }}</div></td>
                <td>@switch($d->status)
                    @case('active')<span class="badge badge-ok">Registered</span>@break
                    @case('waiting')<span class="badge badge-warn">Waiting for code</span>@break
                    @case('expired')<span class="badge">Code expired — ask HR</span>@break
                    @default<span class="badge badge-error">Disabled</span>
                @endswitch</td>
                <td class="small muted">{{ $d->last_used_at ? 'Used '.$d->last_used_at->diffForHumans() : '' }}</td></tr>
        @empty <tr><td class="empty">None yet.</td></tr> @endforelse
        </tbody></table></div>
    </section>
</div>
@endsection
