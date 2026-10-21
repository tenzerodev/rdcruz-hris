@extends('layouts.app')
@section('title', $schedule->name)
@section('content')
@php
    $money = fn ($v) => $v === null ? '—' : '₱'.number_format((float) $v, 2);
    $changes = $adjustments->filter(fn ($a) => $a['new'] !== null && abs($a['new'] - $a['old']) >= 0.005);
    $missing = $adjustments->filter(fn ($a) => $a['new'] === null);
@endphp
<div class="page-head">
    <div><h1>{{ $schedule->name }}</h1><div class="muted">Effective {{ $schedule->effective_date->format('F j, Y') }}{{ $schedule->basis ? ' · '.$schedule->basis : '' }}</div></div>
    <a class="btn" href="{{ route('admin.compensation.index') }}">Back</a>
</div>
@if (session('print'))<div class="alert alert-ok"><x-icon name="file" /><div>Notices ready. <a href="{{ session('print') }}" target="_blank">Print all NOSAs</a>.</div></div>@endif

<section class="card" style="margin-bottom:16px">
    <h2>Salary adjustment (NOSA)</h2>
    <p>{{ $changes->count() }} employee(s) get a new salary under this schedule{{ $applied ? '; '.$applied.' notice(s) already issued' : '' }}.@if ($missing->isNotEmpty()) <span class="badge badge-warn">{{ $missing->count() }} with no amount for their grade/step</span>@endif</p>
    @if ($changes->isNotEmpty())
        <div class="table-wrap" style="max-height:320px;overflow:auto"><table class="table">
            <thead><tr><th>Employee</th><th>SG-step</th><th class="num">Now</th><th class="num">New</th><th class="num">Difference</th></tr></thead><tbody>
            @foreach ($changes as $a)<tr><td>{{ $a['employee']->full_name }}</td><td>{{ $a['employee']->salary_grade }}-{{ $a['employee']->step ?: 1 }}</td><td class="num">{{ $money($a['old']) }}</td><td class="num"><b>{{ $money($a['new']) }}</b></td><td class="num">{{ $money($a['new'] - $a['old']) }}</td></tr>@endforeach
        </tbody></table></div>
        @unless ($readOnly)
        <form method="post" action="{{ route('admin.compensation.apply', $schedule) }}" style="margin-top:12px" onsubmit="return confirm('Apply to {{ $changes->count() }} employee(s)? Salaries, 201 files and service records are updated, and a NOSA is issued for each.')">
            @csrf <button class="btn btn-primary">Apply and issue NOSAs</button>
        </form>
        @endunless
    @endif
    @if ($applied)<p><a class="btn btn-sm" href="{{ route('admin.compensation.notices.print', ['schedule' => $schedule->id]) }}" target="_blank">Print issued NOSAs</a></p>@endif
</section>

<form method="post" action="{{ route('admin.compensation.schedules.update', $schedule) }}" class="card">
    @csrf @method('put')
    <h2>Amounts (monthly)</h2>
    <div class="row">
        <div class="field"><label for="name">Name</label><input type="text" id="name" name="name" value="{{ $schedule->name }}" required></div>
        <div class="field"><label for="effective_date">Effective</label><input type="date" id="effective_date" name="effective_date" value="{{ $schedule->effective_date->toDateString() }}" required></div>
        <div class="field" style="flex:2"><label for="basis">Legal basis</label><input type="text" id="basis" name="basis" value="{{ $schedule->basis }}"></div>
    </div>
    <div class="table-wrap"><table class="table" style="font-size:.82rem">
        <thead><tr><th>SG</th>@for ($s = 1; $s <= 8; $s++)<th>Step {{ $s }}</th>@endfor</tr></thead>
        <tbody>
        @for ($g = 1; $g <= 33; $g++)
            <tr><td><b>{{ $g }}</b></td>
                @for ($s = 1; $s <= 8; $s++)
                    <td><label class="sr" for="r{{ $g }}-{{ $s }}">SG {{ $g }} step {{ $s }}</label><input type="number" step="0.01" min="0" id="r{{ $g }}-{{ $s }}" name="rates[{{ $g }}][{{ $s }}]" value="{{ $grid[$g.'-'.$s] ?? '' }}" style="width:100px;padding:4px 6px" @disabled($readOnly)></td>
                @endfor
            </tr>
        @endfor
        </tbody>
    </table></div>
    @unless ($readOnly)
        <div class="actions" style="justify-content:flex-start;margin-top:12px"><button class="btn btn-primary">Save amounts</button></div>
    @endunless
</form>
@unless ($readOnly || $applied)
    <form method="post" action="{{ route('admin.compensation.schedules.destroy', $schedule) }}" style="margin-top:12px" onsubmit="return confirm('Delete this salary schedule?')">@csrf @method('delete')<button class="btn btn-sm btn-danger">Delete schedule</button></form>
@endunless
@endsection
