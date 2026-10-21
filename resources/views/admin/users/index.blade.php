@extends('layouts.app')
@section('title', 'User accounts')
@section('content')
<div class="page-head">
    <div><h1>User accounts</h1><div class="muted">Who can sign in, and what each role can do. Employee logins are made from the employee's profile; give HR staff a role here.</div></div>
</div>

<div class="grid grid-2" style="margin-bottom:16px">
    <section class="card">
        <h2>Add an HR staff account</h2>
        <form method="post" action="{{ route('admin.users.store') }}">
            @csrf
            <div class="row">
                <div class="field"><label for="name">Full name</label><input type="text" id="name" name="name" value="{{ old('name') }}" required></div>
                <div class="field"><label for="username">Username</label><input type="text" id="username" name="username" value="{{ old('username') }}" required></div>
            </div>
            <div class="row">
                <div class="field"><label for="email">Email</label><input type="email" id="email" name="email" value="{{ old('email') }}"></div>
                <div class="field"><label for="role">Role</label><select id="role" name="role" required>
                    @foreach ($roles as $key => $r)@continue($key === 'employee')<option value="{{ $key }}" @selected(old('role', 'hr') === $key)>{{ $r['label'] }}</option>@endforeach
                </select></div>
            </div>
            <div class="field"><label for="employee_id">Also an employee? Link their record</label><select id="employee_id" name="employee_id"><option value="">— Not linked —</option>
                @foreach ($unlinked as $e)<option value="{{ $e->id }}" @selected(old('employee_id') == $e->id)>{{ $e->last_name }}, {{ $e->first_name }} ({{ $e->employee_no }})</option>@endforeach
            </select><div class="hint">Linked staff can also time in and file their own leave from the same login.</div></div>
            <button class="btn btn-primary">Make account</button>
            <p class="hint">A temporary password is shown after saving. They must change it when they first sign in.</p>
        </form>
    </section>
    <section class="card">
        <h2>What each role can do</h2>
        <div class="table-wrap"><table class="table">
            <tbody>
            @foreach ($roles as $key => $r)
                <tr><td style="width:34%"><b>{{ $r['label'] }}</b></td><td class="small">{{ $r['description'] }}</td></tr>
            @endforeach
            </tbody>
        </table></div>
    </section>
</div>

<section class="card">
    <form class="inline-form" method="get" style="margin-bottom:12px">
        <div class="field" style="flex:1;min-width:200px"><label for="q" class="sr">Search</label><input type="search" id="q" name="q" value="{{ $q }}" placeholder="Name or username"></div>
        <div class="field"><label for="f-role" class="sr">Role</label><select id="f-role" name="role"><option value="">HR staff (all roles except employee)</option>
            @foreach ($roles as $key => $r)<option value="{{ $key }}" @selected(request('role') === $key)>{{ $r['label'] }}</option>@endforeach</select></div>
        <button class="btn">Show</button>
    </form>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Account</th><th>Role</th><th>Two-step</th><th>Last sign-in</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @forelse ($users as $u)
            <tr>
                <td><b>{{ $u->name }}</b><div class="small muted">{{ $u->username }}@if ($u->email) · {{ $u->email }}@endif @if ($u->employee) · employee {{ $u->employee->office }}@endif</div></td>
                <td>
                    <form method="post" action="{{ route('admin.users.update', $u) }}" class="inline-form">@csrf @method('put')
                        <input type="hidden" name="name" value="{{ $u->name }}"><input type="hidden" name="email" value="{{ $u->email }}">
                        <label for="role-{{ $u->id }}" class="sr">Role</label>
                        <select id="role-{{ $u->id }}" name="role" onchange="this.form.submit()">
                            @foreach ($roles as $key => $r)<option value="{{ $key }}" @selected($u->role === $key)>{{ $r['label'] }}</option>@endforeach
                        </select>
                        <noscript><button class="btn btn-sm">Save</button></noscript>
                    </form>
                </td>
                <td>@if ($u->hasTwoFactor())<span class="badge badge-ok">On</span>@else<span class="badge">Off</span>@endif</td>
                <td class="small">{{ $u->last_login_at?->diffForHumans() ?? 'Never' }}@if ($u->must_change_password)<div class="muted">Must change password</div>@endif</td>
                <td>@if ($u->disabled_at)<span class="badge badge-error">Disabled</span>@else<span class="badge badge-ok">Active</span>@endif</td>
                <td><div class="actions">
                    <form method="post" action="{{ route('admin.users.reset-password', $u) }}" onsubmit="return confirm('Make a new temporary password for {{ $u->username }}?')">@csrf<button class="btn btn-sm">Reset password</button></form>
                    @if ($u->hasTwoFactor())
                        <form method="post" action="{{ route('admin.users.reset-2fa', $u) }}" onsubmit="return confirm('Turn off two-step verification for {{ $u->username }}? Do this only after confirming who is asking.')">@csrf<button class="btn btn-sm">Reset 2-step</button></form>
                    @endif
                    @unless ($u->is(auth()->user()))
                        <form method="post" action="{{ route('admin.users.toggle', $u) }}">@csrf<button class="btn btn-sm {{ $u->disabled_at ? '' : 'btn-danger' }}">{{ $u->disabled_at ? 'Enable' : 'Disable' }}</button></form>
                    @endunless
                </div></td>
            </tr>
        @empty <tr><td colspan="6" class="empty">No accounts match.</td></tr> @endforelse
        </tbody>
    </table></div>
    <div class="pagination">{{ $users->links() }}</div>
</section>
@endsection
