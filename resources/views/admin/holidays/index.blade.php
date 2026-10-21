@extends('layouts.app')
@section('title', 'Holidays')
@section('content')
<div class="page-head">
    <div><h1>Holidays {{ $year }}</h1><div class="muted">Shown on Form 48 and excluded from undertime and absences.</div></div>
    <div class="actions"><x-import-button key="holidays" />
    <form class="inline-form" method="get"><div class="field"><label for="year">Year</label><input type="number" id="year" name="year" value="{{ $year }}" min="2000" max="2100" style="width:110px"></div><button class="btn">Go</button></form></div>
</div>
<div class="grid grid-2">
    <section class="card">
        <div class="table-wrap"><table class="table">
            <tbody>
            @forelse ($holidays as $h)
                <tr><td class="num">{{ $h->date->format('M j, D') }}</td><td>{{ $h->name }} <span class="badge">{{ \App\Models\Holiday::TYPES[$h->type] ?? $h->type }}</span></td>
                    <td><form method="post" action="{{ route('admin.holidays.destroy', $h) }}" class="actions">@csrf @method('delete')<button class="btn btn-sm btn-danger">Remove</button></form></td></tr>
            @empty
                <tr><td class="muted">No holidays for {{ $year }}.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </section>
    <section class="card">
        <h2>Add holiday</h2>
        <form method="post" action="{{ route('admin.holidays.store') }}">
            @csrf
            <div class="field"><label for="date">Date</label><input type="date" id="date" name="date" required></div>
            <div class="field"><label for="hname">Name</label><input type="text" id="hname" name="name" placeholder="Independence Day" required></div>
            <div class="field"><label for="htype">Type</label><select id="htype" name="type">@foreach (\App\Models\Holiday::TYPES as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
            <button class="btn btn-primary">Add</button>
        </form>
        <p class="hint">Add regular and special non-working days from the Presidential proclamation for the year, plus local holidays and work suspensions.</p>
    </section>
</div>
@endsection
