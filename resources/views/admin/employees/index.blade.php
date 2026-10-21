@extends('layouts.app')
@section('title', 'Employees')
@section('content')
@php $group = request('group'); @endphp
<div class="page-head">
    <h1>Employees</h1>
    <div class="actions">
        <a class="btn btn-primary" href="{{ route('admin.employees.create') }}">Add employee</a>
        <a class="btn" href="{{ route('admin.employees.create', ['type' => 'cos']) }}">Add COS / JO</a>
        <a class="btn" href="{{ route('admin.employees.create', ['type' => 'service_provider']) }}">Add service provider</a>
        <a class="btn" href="{{ route('admin.employees.create', ['type' => 'dbp']) }}">Add DBP personnel</a>
        <x-import-button key="employees" />
    </div>
</div>
<nav class="tabs" aria-label="Employee groups">
    <a href="{{ route('admin.employees.index', request()->except('group', 'page')) }}" @if (! $group) aria-current="page" @endif>All</a>
    @foreach (config('hris.groups') as $key => $label)
        <a href="{{ route('admin.employees.index', ['group' => $key] + request()->except('group', 'page')) }}" @if ($group === $key) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
</nav>
<div class="card">
    <form class="inline-form" method="get" style="margin-bottom:12px">
        @if ($group)<input type="hidden" name="group" value="{{ $group }}">@endif
        <div class="field" style="flex:1;min-width:200px"><label for="q" class="sr">Search</label><input type="search" id="q" name="q" value="{{ $q }}" placeholder="Name, employee no., office"></div>
        <div class="field"><label for="status">Status</label><select id="status" name="status"><option value="">Active</option><option value="inactive" @selected(request('status') === 'inactive')>Inactive</option></select></div>
        <button class="btn">Filter</button>
    </form>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>ID no.</th><th>Name</th><th>Type</th><th>Position / Office</th><th>Face</th><th>Login</th><th></th></tr></thead>
        <tbody>
        @forelse ($employees as $e)
            <tr>
                <td class="num">{{ $e->employee_no }}</td>
                <td><a href="{{ route('admin.employees.show', $e) }}">{{ $e->full_name }}</a></td>
                <td><span class="badge">{{ $e->type_label }}</span>
                    @if ($e->agency_name)<div class="small muted">{{ $e->agency_name }}</div>@endif
                    @if ($e->contractEndsSoon())<div><span class="badge badge-warn">ends {{ $e->contract_end->format('M j') }}</span></div>@endif</td>
                <td>{{ $e->position }}<div class="small muted">{{ $e->office }}</div></td>
                <td>@if ($e->face_samples_count)<span class="badge badge-ok">enrolled</span>@else<span class="badge badge-warn">not enrolled</span>@endif</td>
                <td>@if ($e->user)<span class="badge badge-info">{{ $e->user->username }}</span>@else<span class="muted small">kiosk only</span>@endif</td>
                <td><div class="actions">
                    <a class="btn btn-sm" href="{{ route('admin.employees.show', $e) }}">Open</a>
                    <a class="btn btn-sm" href="{{ route('admin.employees.edit', $e) }}">Edit</a>
                </div></td>
            </tr>
        @empty
            <tr><td colspan="7" class="muted">No employees found.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    <div class="pagination">{{ $employees->links() }}</div>
</div>
@endsection
