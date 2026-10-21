@extends('layouts.app')
@section('title', 'Kiosks')
@section('content')
<div class="page-head"><div><h1>Kiosks</h1><div class="muted">Shared devices (tablet, phone, laptop) where anyone enrolled can time in.</div></div></div>
<div class="grid grid-2">
    <section class="card">
        <h2>Devices</h2>
        @forelse ($kiosks as $k)
            <div style="border-bottom:1px solid var(--border);padding:12px 0">
                <form method="post" action="{{ route('admin.kiosks.update', $k) }}" class="inline-form">
                    @csrf @method('put')
                    <div class="field" style="flex:1;min-width:140px"><label>Name</label><input type="text" name="name" value="{{ $k->name }}" required></div>
                    <div class="field" style="flex:1;min-width:140px"><label>Location</label><input type="text" name="location" value="{{ $k->location }}"></div>
                    <label class="check"><input type="checkbox" name="is_active" value="1" @checked($k->is_active)> Active</label>
                    <button class="btn btn-sm">Save</button>
                </form>
                <div class="actions" style="justify-content:flex-start;margin-top:8px">
                    <form method="post" action="{{ route('admin.kiosks.activate', $k) }}" onsubmit="return confirm('Make THIS browser the &quot;{{ $k->name }}&quot; kiosk? Any other device using it will stop working.')">@csrf<button class="btn btn-primary btn-sm">Activate this device</button></form>
                    <form method="post" action="{{ route('admin.kiosks.destroy', $k) }}" onsubmit="return confirm('Remove this kiosk?')">@csrf @method('delete')<button class="btn btn-danger btn-sm">Remove</button></form>
                    <span class="small muted">{{ $k->token_hash ? 'Activated' : 'Not activated' }} · last seen {{ $k->last_seen_at?->diffForHumans() ?? 'never' }}</span>
                </div>
            </div>
        @empty
            <p class="muted">No kiosks yet.</p>
        @endforelse
    </section>
    <section class="card">
        <h2>Add kiosk</h2>
        <form method="post" action="{{ route('admin.kiosks.store') }}">
            @csrf
            <div class="field"><label for="name">Name</label><input type="text" id="name" name="name" placeholder="Main entrance" required></div>
            <div class="field"><label for="location">Location</label><input type="text" id="location" name="location" placeholder="Ground floor lobby"></div>
            <button class="btn btn-primary">Add kiosk</button>
        </form>
        <h2 style="margin-top:20px">How to set up a kiosk</h2>
        <ol class="small">
            <li>On the kiosk device, open this app over <b>https</b> and log in as admin.</li>
            <li>Go to <b>Kiosks</b> and tap <b>Activate this device</b>.</li>
            <li>You can now log out. The device stays a kiosk at <code>/kiosk</code>.</li>
            <li>Tip: pin the browser to full screen and keep the device plugged in.</li>
        </ol>
    </section>
</div>
@endsection
