@extends('layouts.app')
@section('title', 'Pass slips')
@section('content')
<div class="page-head"><div><h1>Pass slips</h1><div class="muted">For leaving the office for a short time during work hours. Official trips (locator slip) are excused on your DTR; personal ones count as undertime.</div></div></div>
<div class="grid grid-2">
    <section class="card">
        <h2>New pass slip</h2>
        <form method="post" action="{{ route('me.pass-slips') }}">@csrf
            @include('me._pass-slip-fields')
            <button class="btn btn-primary">Send to supervisor</button>
        </form>
        <p class="hint">Personal pass slips: up to {{ $limits['personal_per_month'] }} a month, {{ $limits['personal_max_minutes'] / 60 }} hours each. You've used {{ $used }} this month.</p>
    </section>
    <section class="card">
        <h2>My pass slips</h2>
        <div class="table-wrap"><table class="table"><tbody>
        @forelse ($slips as $p)
            <tr><td><b>{{ $p->date->format('D, M j') }}</b> · {{ $p->type === 'official' ? 'Official' : 'Personal' }}<div class="small">{{ $p->times_label }}@if ($p->actual_out) <span class="muted">(recorded)</span>@endif</div><div class="small muted">{{ $p->destination }} — {{ $p->purpose }}</div></td>
                <td>@include('admin.leave._status', ['status' => $p->status])</td>
                <td><div class="actions">
                    @if ($p->status === 'approved' && $p->date->isToday())
                        @if (! $p->actual_out)
                            <form method="post" action="{{ route('me.pass-slips.clock', $p) }}">@csrf<input type="hidden" name="event" value="out"><button class="btn btn-sm btn-primary">Leaving now</button></form>
                        @elseif (! $p->actual_back && $p->time_back)
                            <form method="post" action="{{ route('me.pass-slips.clock', $p) }}">@csrf<input type="hidden" name="event" value="back"><button class="btn btn-sm btn-primary">I’m back</button></form>
                        @endif
                    @endif
                    @if (in_array($p->status, ['pending', 'approved'], true) && ! $p->actual_out)
                        <form method="post" action="{{ route('me.pass-slips.cancel', $p) }}">@csrf<button class="btn btn-sm">Cancel</button></form>
                    @endif
                </div></td></tr>
        @empty <tr><td class="muted">No pass slips yet.</td></tr> @endforelse
        </tbody></table></div>
    </section>
</div>
@endsection
