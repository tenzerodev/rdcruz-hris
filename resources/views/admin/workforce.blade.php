@extends('layouts.app')
@section('title', 'Workforce')
@section('content')
@php $groups = config('hris.groups'); @endphp
<div class="page-head"><div><h1>Workforce</h1><div class="muted">Active personnel by employment group, office and agency.</div></div><button class="btn" onclick="window.print()">Print</button></div>
<div class="grid grid-4" style="margin-bottom:16px">
    @foreach ($groups as $k => $l)
        <a class="card stat" href="{{ route('admin.employees.index', ['group' => $k]) }}" style="text-decoration:none;color:inherit"><div class="num">{{ ($byGroup[$k] ?? collect())->count() }}</div><div class="label">{{ $l }}</div></a>
    @endforeach
</div>
<div class="grid grid-2">
    <section class="card">
        <h2>By office</h2>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Office</th>@foreach ($groups as $k => $l)<th class="num">{{ ['plantilla' => 'Plantilla', 'cos' => 'COS/JO', 'service_provider' => 'Service prov.', 'dbp' => 'DBP'][$k] }}</th>@endforeach<th class="num">Total</th></tr></thead><tbody>
            @foreach ($byOffice as $office => $counts)
                <tr><td>{{ $office }}</td>@foreach ($groups as $k => $l)<td class="num">{{ $counts[$k] ?? '' }}</td>@endforeach<td class="num"><b>{{ $counts->sum() }}</b></td></tr>
            @endforeach
            </tbody></table></div>
        <h2 style="margin-top:16px">By employment type</h2>
        @foreach (config('hris.employment_types') as $k => $t)
            @if ($byType[$k] ?? 0)<span class="badge" style="margin:0 4px 6px 0">{{ $t['label'] }}: {{ $byType[$k] }}</span>@endif
        @endforeach
        <h2 style="margin-top:16px">Plantilla</h2>
        <p class="small">{{ $positions->count() }} funded items · {{ $positions->filter->employee->count() }} filled · <b>{{ $positions->reject->employee->count() }} vacant</b></p>
    </section>
    <div class="stack">
        <section class="card">
            <h2>Contracts (COS, JO, service providers, casual/contractual)</h2>
            <div class="table-wrap"><table class="table"><tbody>
            @forelse ($contracts as $e)
                <tr><td><a href="{{ route('admin.employees.show', $e) }}">{{ $e->full_name }}</a><div class="small muted">{{ $e->type_label }}{{ $e->agency_name ? ' · '.$e->agency_name : '' }}</div></td>
                    <td class="num small">{{ $e->contract_start?->format('M j, Y') }} – {{ $e->contract_end->format('M j, Y') }}</td>
                    <td>@if ($e->contract_end->isPast())<span class="badge badge-error">expired</span>@elseif ($e->contractEndsSoon())<span class="badge badge-warn">{{ (int) today()->diffInDays($e->contract_end) }} days left</span>@endif</td></tr>
            @empty <tr><td class="muted">No contract end dates on file.</td></tr> @endforelse
            </tbody></table></div>
        </section>
        <section class="card">
            <h2>Service providers & DBP personnel by agency</h2>
            @forelse ($agencies as $agency => $people)
                <div style="margin-bottom:8px"><b>{{ $agency }}</b> <span class="badge">{{ $people->count() }}</span>
                    <div class="small muted">{{ $people->map->full_name->join('; ') }}</div></div>
            @empty <p class="muted">None.</p> @endforelse
        </section>
    </div>
</div>
@endsection
