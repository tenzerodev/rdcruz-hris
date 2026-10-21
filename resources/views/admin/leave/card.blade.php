@extends('layouts.app')
@section('title', 'Leave card')
@section('content')
@php $n = fn ($v) => $v == 0 ? '' : rtrim(rtrim(number_format($v, 3), '0'), '.'); @endphp
<div class="page-head">
    <div><h1>Leave card {{ $year }}</h1><div class="muted"><a href="{{ route('admin.employees.show', [$employee, 'tab' => 'leave']) }}">{{ $employee->full_name }}</a> · {{ $employee->type_label }}</div></div>
    <form class="inline-form" method="get"><div class="field"><label for="year">Year</label><input type="number" id="year" name="year" value="{{ $year }}" style="width:100px"></div><button class="btn">Go</button>
        <button class="btn" type="button" onclick="window.print()">Print</button></form>
</div>
<div class="grid grid-4" style="margin-bottom:16px">
    @foreach (config('hris.balances') as $k => $l)
        <div class="card stat"><div class="num">{{ $n($balances[$k]) ?: '0' }}</div><div class="label">{{ $l }} — current</div></div>
    @endforeach
</div>
@foreach (config('hris.balances') as $bal => $label)
    @php $rows = $entries->where('balance', $bal); @endphp
    @continue($rows->isEmpty() && ! $opening[$bal])
    <section class="card" style="margin-bottom:16px">
        <h2>{{ $label }}</h2>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Date</th><th>Particulars</th><th>Earned</th><th>Used / deducted</th><th>Balance</th><th></th></tr></thead>
            <tbody>
                @php $running = $opening[$bal]; @endphp
                <tr><td></td><td class="muted">Balance forwarded</td><td></td><td></td><td class="num"><b>{{ $n($running) ?: '0' }}</b></td><td></td></tr>
                @foreach ($rows as $r)
                    @php $running += $r->earned - $r->used; @endphp
                    <tr><td class="num small">{{ $r->entry_date->format('M j') }}</td><td>{{ $r->particulars }}</td>
                        <td class="num">{{ $n($r->earned) }}</td><td class="num">{{ $n($r->used) }}</td><td class="num"><b>{{ $n($running) ?: '0' }}</b></td>
                        <td>@if ($r->source === 'adjustment')<form method="post" action="{{ route('admin.leave-card.destroy', $r) }}" onsubmit="return confirm('Remove this adjustment?')">@csrf @method('delete')<button class="btn btn-sm btn-danger" aria-label="Remove">✕</button></form>@endif</td></tr>
                @endforeach
            </tbody>
        </table></div>
    </section>
@endforeach
<section class="card narrow">
    <h2>Adjustment / beginning balance</h2>
    <p class="hint">Use this to enter balances brought over from the old leave cards, or to correct an entry. It's saved in the audit log.</p>
    <form method="post" action="{{ route('admin.employees.leave-card.adjust', $employee) }}">@csrf
        <div class="row">
            <div class="field"><label for="balance">Balance</label><select id="balance" name="balance">@foreach (config('hris.balances') as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
            <div class="field"><label for="entry_date">Date</label><input type="date" id="entry_date" name="entry_date" value="{{ now()->toDateString() }}" required></div>
        </div>
        <div class="field"><label for="particulars">Particulars</label><input type="text" id="particulars" name="particulars" placeholder="Balance as of Dec 31, 2025" required></div>
        <div class="row">
            <div class="field"><label for="earned">Add (earned)</label><input type="number" step="0.001" min="0" id="earned" name="earned"></div>
            <div class="field"><label for="used">Deduct (used)</label><input type="number" step="0.001" min="0" id="used" name="used"></div>
        </div>
        <button class="btn btn-primary">Save</button>
    </form>
</section>
@endsection
