@extends($print ? 'print._layout' : 'layouts.app')
@section('title', 'Loyalty awards '.$year)
@section('content')
@php $money = fn ($v) => '₱'.number_format((float) $v, 2); @endphp
@if ($print)
    <x-print-header /><h1>LOYALTY AWARDEES — {{ $year }}</h1>
@else
<div class="page-head">
    <div><h1>Loyalty awards — {{ $year }}</h1><div class="muted">CSC loyalty award: at {{ config('hris.loyalty.first') }} years of continuous service in the agency, then every {{ config('hris.loyalty.every') }} years. Counted from the date hired.</div></div>
    <div class="actions">
        <form class="inline-form" method="get"><div class="field"><label for="year" class="sr">Year</label><input type="number" id="year" name="year" value="{{ $year }}" style="width:100px"></div><button class="btn">Show</button></form>
        <a class="btn" href="{{ request()->fullUrlWithQuery(['print' => 1]) }}" target="_blank">Print list</a>
    </div>
</div>
@endif
<section @class(['card' => ! $print])>
    <div class="table-wrap"><table @class(['table' => ! $print, 'f' => $print])>
        <thead><tr><th>Employee</th><th>Position / office</th><th>Date hired</th><th>Years</th><th>Milestone date</th><th class="num">Cash award</th>@unless ($print)<th></th>@endunless</tr></thead>
        <tbody>
        @forelse ($rows as $r)
            <tr><td><b>{{ $r['employee']->full_name }}</b></td><td class="small">{{ $r['employee']->position }}<div class="muted">{{ $r['employee']->office }}</div></td>
                <td>{{ $r['employee']->date_hired->format('M j, Y') }}</td><td><b>{{ $r['years'] }}</b></td><td>{{ $r['date']->format('M j, Y') }}</td>
                <td class="num">{{ $money($r['award']?->amount ?? $r['amount']) }}</td>
                @unless ($print)<td>@if ($r['award']?->awarded_at)<span class="badge badge-ok">Awarded {{ $r['award']->awarded_at->format('M j, Y') }}</span>
                    @elseif (! $readOnly)<form method="post" action="{{ route('admin.reports.loyalty.award', $r['employee']) }}" class="inline-form">@csrf
                        <input type="hidden" name="years" value="{{ $r['years'] }}"><div class="field"><label class="sr" for="amt{{ $r['employee']->id }}">Amount</label><input type="number" id="amt{{ $r['employee']->id }}" name="amount" value="{{ $r['amount'] }}" step="0.01" style="width:110px"></div>
                        <button class="btn btn-sm btn-primary">Mark awarded</button></form>@endif</td>@endunless
            </tr>
        @empty <tr><td colspan="7" class="empty">No one reaches a loyalty milestone in {{ $year }}.</td></tr> @endforelse
        </tbody></table></div>
    @unless ($print)<p class="hint">Service elsewhere in government or leave without pay may change the count — check the service record. Amounts are in config/hris.php (loyalty).</p>@endunless
</section>
@endsection
