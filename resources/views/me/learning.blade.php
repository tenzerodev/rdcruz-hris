@extends('layouts.app')
@section('title', 'Training')
@section('content')
<div class="page-head"><div><h1>Training</h1><div class="muted">Your learning and development record, nominations, and the trainings you'd like.</div></div></div>
<div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(170px,1fr));margin-bottom:16px">
    <div class="card stat"><div class="num">{{ $hours }}</div><div class="label">Training hours in {{ $year }} (target {{ $target }})</div></div>
    <div class="card stat"><div class="num">{{ $nominations->count() }}</div><div class="label">Upcoming nominations</div></div>
</div>
<div class="grid grid-2">
    <div class="stack">
        @if ($nominations->isNotEmpty())
        <section class="card">
            <h2>You are nominated for</h2>
            <table class="table"><tbody>@foreach ($nominations as $n)
                <tr><td><b>{{ $n->program->title }}</b><div class="small muted">{{ $n->program->when_label }}{{ $n->program->hours ? ' · '.$n->program->hours.' hours' : '' }}{{ $n->program->provider ? ' · '.$n->program->provider : '' }}</div></td></tr>
            @endforeach</tbody></table>
        </section>
        @endif
        <section class="card">
            <h2>My trainings (201 / PDS)</h2>
            <table class="table"><tbody>
            @forelse ($trainings as $t)
                <tr><td>{{ $t->title }}<div class="small muted">{{ $t->date_from->format('M j, Y') }}{{ $t->hours ? ' · '.$t->hours.' hours' : '' }}{{ $t->sponsor ? ' · '.$t->sponsor : '' }}</div></td></tr>
            @empty <tr><td class="muted">No trainings recorded yet.</td></tr> @endforelse
            </tbody></table>
            <p class="hint">Attended a training not listed? Give HR a copy of the certificate.</p>
        </section>
    </div>
    <div class="stack">
        <section class="card">
            <h2>What would help you do your job better?</h2>
            <form method="post" action="{{ route('me.learning.needs') }}">@csrf
                <div class="row">
                    <div class="field"><label for="n_year">For</label><select id="n_year" name="year"><option>{{ $year }}</option><option selected>{{ $year + 1 }}</option></select></div>
                    <div class="field"><label for="n_pri">Priority</label><select id="n_pri" name="priority">@foreach (\App\Models\TrainingNeed::PRIORITIES as $k => $l)<option value="{{ $k }}" @selected($k === 'medium')>{{ $l }}</option>@endforeach</select></div>
                </div>
                <div class="field"><label for="n_comp">Skill or competency to develop</label><input type="text" id="n_comp" name="competency" required placeholder="e.g. Data analysis"></div>
                <div class="field"><label for="n_title">Training you suggest</label><input type="text" id="n_title" name="training_title" placeholder="e.g. Advanced Excel"></div>
                <div class="field"><label for="n_reason">Why</label><input type="text" id="n_reason" name="reason"></div>
                <button class="btn btn-primary">Submit</button>
            </form>
        </section>
        @if ($needs->isNotEmpty())
        <section class="card">
            <h2>My requests</h2>
            <table class="table"><tbody>@foreach ($needs as $n)
                <tr><td>{{ $n->training_title ?: $n->competency }}<div class="small muted">{{ $n->year }} · {{ \App\Models\TrainingNeed::PRIORITIES[$n->priority] }}</div></td>
                    <td>@if ($n->program)<span class="badge badge-ok">In the plan</span>@else<form method="post" action="{{ route('me.learning.needs.destroy', $n) }}">@csrf @method('delete')<button class="btn btn-sm">Remove</button></form>@endif</td></tr>
            @endforeach</tbody></table>
        </section>
        @endif
    </div>
</div>
@endsection
