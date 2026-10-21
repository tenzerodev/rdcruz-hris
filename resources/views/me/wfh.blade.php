@extends('layouts.app')
@section('title', 'Work from home')
@section('content')
<div class="page-head">
    <div><h1>Work from home</h1><div class="muted">Your work-from-home schedule set by HR, and your daily accomplishment reports.</div></div>
    <div class="actions">
        <a class="btn" href="{{ route('me.devices') }}"><x-icon name="monitor" size="16" /> My devices</a>
        <a class="btn" href="{{ route('print.wfh', ['employee' => $e->id, 'month' => $month->format('Y-m')]) }}" target="_blank">Print {{ $month->format('F') }} report</a>
    </div>
</div>

<div class="grid grid-2" style="margin-bottom:16px">
    <section class="card">
        <h2>My schedule</h2>
        @if ($today)<p><span class="badge badge-info">Today is a work-from-home day</span></p>@endif
        <div class="table-wrap"><table class="table"><tbody>
        @forelse ($arrangements as $a)
            <tr><td class="num">{{ $a->date_from->format('M j') }} – {{ $a->date_to->format('M j, Y') }}</td><td>{{ $a->days_label }}</td>
                <td class="small">{{ $a->require_device ? 'From your issued computer' : 'Any device' }}@if ($a->reference_no)<div class="muted">{{ $a->reference_no }}</div>@endif</td></tr>
        @empty <tr><td class="empty">You have no work-from-home arrangement. HR sets these.</td></tr> @endforelse
        </tbody></table></div>
    </section>
    <section class="card">
        <h2>Write accomplishments</h2>
        @if (count($days))
            <form method="post" action="{{ route('me.wfh.report') }}">
                @csrf
                <div class="field"><label for="work_date">Day</label><select id="work_date" name="work_date">
                    @foreach ($days as $d)<option value="{{ $d->toDateString() }}" @selected(old('work_date') === $d->toDateString())>{{ $d->format('D, M j') }}{{ isset($reports[$d->toDateString()]) ? ' ✓' : '' }}</option>@endforeach
                </select></div>
                <div class="field"><label for="accomplishments">What you accomplished</label>
                    <textarea id="accomplishments" name="accomplishments" rows="6" required placeholder="One output per line, e.g.&#10;Drafted 3 exhibitor contracts for review&#10;Attended online coordination meeting with DTI (2 hrs)">{{ old('accomplishments', $reports[$days[0]->toDateString()]->accomplishments ?? '') }}</textarea>
                    <div class="hint">Saving again for the same day replaces it.</div></div>
                <button class="btn btn-primary">Save</button>
            </form>
        @else
            <p class="muted">No work-from-home days in {{ $month->format('F Y') }} so far.</p>
        @endif
    </section>
</div>

<section class="card">
    <div class="page-head" style="margin-bottom:8px">
        <h2 style="margin:0">{{ $month->format('F Y') }}</h2>
        <form class="inline-form" method="get"><div class="field"><label for="month" class="sr">Month</label><input type="month" id="month" name="month" value="{{ $month->format('Y-m') }}"></div><button class="btn btn-sm">Show</button></form>
    </div>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Day</th><th>Accomplishments</th></tr></thead><tbody>
        @forelse ($days as $d)
            @php $r = $reports[$d->toDateString()] ?? null; @endphp
            <tr><td style="white-space:nowrap">{{ $d->format('D, M j') }}</td>
                <td>@if ($r)<div style="white-space:pre-line">{{ $r->accomplishments }}</div>@else<span class="badge badge-warn">Not written yet</span>@endif</td></tr>
        @empty <tr><td colspan="2" class="empty">No work-from-home days.</td></tr> @endforelse
        </tbody></table></div>
</section>
@endsection
