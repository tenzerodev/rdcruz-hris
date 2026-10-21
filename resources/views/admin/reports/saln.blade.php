@extends('layouts.app')
@section('title', 'SALN '.$year)
@section('content')
@php $missing = $employees->filter(fn ($e) => ! isset($filed[$e->id])); @endphp
<div class="page-head">
    <div><h1>SALN filing — as of December 31, {{ $year }}</h1><div class="muted">Due April 30, {{ $year + 1 }} (R.A. No. 6713). {{ $filed->count() }} received · <b>{{ $missing->count() }} not yet filed</b>.</div></div>
    <div class="actions">
        <form class="inline-form" method="get"><div class="field"><label for="year" class="sr">Year</label><input type="number" id="year" name="year" value="{{ $year }}" style="width:100px"></div><button class="btn">Show</button></form>
        <a class="btn" href="{{ request()->fullUrlWithQuery(['format' => 'csv']) }}">Download CSV</a>
    </div>
</div>
<section class="card">
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Employee</th><th>Status</th><th></th></tr></thead><tbody>
        @foreach ($employees->sortBy(fn ($e) => isset($filed[$e->id]) ? 1 : 0) as $e)
            @php $s = $filed[$e->id] ?? null; @endphp
            <tr><td><b>{{ $e->full_name }}</b><div class="small muted">{{ $e->position }} · {{ $e->office }}</div></td>
                <td>@if ($s)<span class="badge badge-ok">Filed {{ $s->filed_on->format('M j, Y') }}</span><div class="small muted">{{ \App\Models\Saln::FILING[$s->filing] }}{{ $s->remarks ? ' · '.$s->remarks : '' }} · received by {{ $s->receiver?->name }}</div>
                    @else<span class="badge {{ now()->gt(\Carbon\Carbon::create($year + 1, 4, 30)->endOfDay()) ? 'badge-error' : 'badge-warn' }}">Not filed</span>@endif</td>
                <td>@unless ($readOnly)<form method="post" action="{{ route('admin.reports.saln.record', $e) }}" class="inline-form">@csrf
                    <input type="hidden" name="year" value="{{ $year }}">
                    <div class="field"><label class="sr" for="d{{ $e->id }}">Filed on</label><input type="date" id="d{{ $e->id }}" name="filed_on" value="{{ $s?->filed_on->toDateString() ?? today()->toDateString() }}"></div>
                    <div class="field"><label class="sr" for="f{{ $e->id }}">Filing</label><select id="f{{ $e->id }}" name="filing">@foreach (\App\Models\Saln::FILING as $k => $l)<option value="{{ $k }}" @selected(($s?->filing ?? 'joint') === $k)>{{ $l }}</option>@endforeach</select></div>
                    <button class="btn btn-sm">{{ $s ? 'Update' : 'Mark received' }}</button></form>@endunless</td></tr>
        @endforeach
        </tbody></table></div>
    <p class="hint">Plantilla, casual and contractual personnel. Contract of service, job order and agency-paid personnel are not required to file with the agency.</p>
</section>
@endsection
