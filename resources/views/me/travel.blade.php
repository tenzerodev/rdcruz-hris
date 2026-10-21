@extends('layouts.app')
@section('title', 'Travel / OB')
@section('content')
<div class="page-head"><h1>Travel, official business & WFH</h1></div>
<div class="grid grid-2">
    <section class="card">
        <h2>New request</h2>
        <form method="post" action="{{ route('me.travel') }}">@csrf
            @include('me._travel-fields')
            <button class="btn btn-primary">Send to supervisor</button>
        </form>
    </section>
    <section class="card">
        <h2>My requests</h2>
        <table class="table"><tbody>
        @forelse ($orders as $o)
            <tr><td>{{ $o->type_name }} — {{ $o->destination }}<div class="small muted">{{ $o->date_from->format('M j') }}{{ $o->date_to->ne($o->date_from) ? '–'.$o->date_to->format('M j') : '' }}, {{ $o->date_from->format('Y') }}{{ $o->time_from ? ' · '.$o->time_from.'–'.$o->time_to : '' }}</div></td>
                <td>@include('admin.leave._status', ['status' => $o->status])</td>
                <td><div class="actions">
                    @if ($o->status === 'approved')<a class="btn btn-sm" href="{{ route('print.travel', $o) }}" target="_blank">Print</a>@endif
                    @if ($o->status === 'pending' && $o->requested_by === auth()->id())<form method="post" action="{{ route('me.travel.cancel', $o) }}">@csrf<button class="btn btn-sm">Cancel</button></form>@endif
                </div></td></tr>
        @empty <tr><td class="muted">No requests yet.</td></tr> @endforelse
        </tbody></table>
    </section>
</div>
@endsection
