@extends('layouts.app')
@section('title', 'Travel / OB')
@section('content')
<div class="page-head"><div><h1>Travel orders & official business</h1><div class="muted">Approved orders excuse the days on the DTR (no absence, no undertime).</div></div><x-import-button key="travel" /></div>
<nav class="tabs">
    @foreach (['pending' => 'Pending', 'approved' => 'Approved', 'disapproved' => 'Disapproved', 'cancelled' => 'Cancelled', 'all' => 'All'] as $k => $l)
        <a href="{{ route('admin.travel.index', ['status' => $k]) }}" @if ($status === $k) aria-current="page" @endif>{{ $l }}</a>
    @endforeach
</nav>
<div class="grid" style="grid-template-columns: minmax(0, 2fr) minmax(280px, 1fr)">
    <section class="card table-wrap">
        <table class="table">
            <thead><tr><th>Type</th><th>Personnel</th><th>Dates</th><th>Destination / purpose</th><th>Status</th><th></th></tr></thead><tbody>
            @forelse ($orders as $o)
                <tr>
                    <td>{{ $o->type_name }}<div class="small muted">{{ $o->reference_no }}</div></td>
                    <td class="small">{{ $o->employees->map->full_name->join('; ') }}</td>
                    <td class="num small">{{ $o->date_from->format('M j') }}{{ $o->date_to->ne($o->date_from) ? '–'.$o->date_to->format('M j') : '' }}, {{ $o->date_from->format('Y') }}{{ $o->time_from ? ' · '.$o->time_from.'–'.$o->time_to : '' }}</td>
                    <td>{{ $o->destination }}<div class="small muted">{{ $o->purpose }}</div></td>
                    <td>@include('admin.leave._status', ['status' => $o->status])</td>
                    <td><div class="actions">
                        @if ($o->status === 'pending')
                            <form method="post" action="{{ route('admin.travel.decide', $o) }}">@csrf<input type="hidden" name="decision" value="approve"><button class="btn btn-sm btn-primary">Approve</button></form>
                            <form method="post" action="{{ route('admin.travel.decide', $o) }}">@csrf<input type="hidden" name="decision" value="disapprove"><button class="btn btn-sm btn-danger">Disapprove</button></form>
                        @elseif ($o->status === 'approved')
                            <a class="btn btn-sm" href="{{ route('print.travel', $o) }}" target="_blank">Print</a>
                            <form method="post" action="{{ route('admin.travel.decide', $o) }}" onsubmit="return confirm('Cancel this order?')">@csrf<input type="hidden" name="decision" value="cancel"><button class="btn btn-sm">Cancel</button></form>
                        @endif
                    </div></td>
                </tr>
            @empty <tr><td colspan="6" class="muted">Nothing here.</td></tr> @endforelse
            </tbody></table>
        <div class="pagination">{{ $orders->links() }}</div>
    </section>
    <section class="card">
        <h2>Issue an order</h2>
        <form method="post" action="{{ route('admin.travel.store') }}">@csrf
            <div class="field"><label for="employee_ids">Personnel</label><select id="employee_ids" name="employee_ids[]" multiple size="6" required>
                @foreach ($employees as $e)<option value="{{ $e->id }}">{{ $e->full_name }}</option>@endforeach</select><div class="hint">Hold Ctrl/⌘ to pick several.</div></div>
            @include('me._travel-fields')
            <div class="field"><label for="reference_no">Order / memo no.</label><input type="text" id="reference_no" name="reference_no"></div>
            <button class="btn btn-primary">Issue (approved)</button>
        </form>
    </section>
</div>
@endsection
