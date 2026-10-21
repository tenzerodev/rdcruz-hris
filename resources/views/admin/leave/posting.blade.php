@extends('layouts.app')
@section('title', 'Leave card posting')
@section('content')
<div class="page-head"><h1>Leave card posting</h1><x-import-button key="leave_balances">Import beginning balances</x-import-button></div>
<div class="grid grid-2">
    <section class="card">
        <h2>Month-end</h2>
        <p class="small">For everyone with leave credits: adds <b>1.25 VL and 1.25 SL</b>, charges the month's <b>tardiness/undertime to VL</b> (8 hours = 1 day), and adds the <b>COC hours</b> earned from approved overtime. Running it again for the same month replaces that month's postings, so you can re-post after DTR corrections.</p>
        <form class="inline-form" method="post" action="{{ route('admin.leave.post') }}">@csrf
            <input type="hidden" name="kind" value="month">
            <div class="field"><label for="month">Month</label><input type="month" id="month" name="month" value="{{ now()->subMonthNoOverflow()->format('Y-m') }}" required></div>
            <button class="btn btn-primary">Post month</button>
        </form>
    </section>
    <section class="card">
        <h2>Year-end</h2>
        <p class="small">Deducts <b>forced leave not taken</b> (5 days, for those with 10+ VL), lapses unused <b>SPL</b>, and grants next year's <b>3 SPL days</b>. Safe to re-run.</p>
        <form class="inline-form" method="post" action="{{ route('admin.leave.post') }}" onsubmit="return confirm('Post year-end leave entries?')">@csrf
            <input type="hidden" name="kind" value="year">
            <div class="field"><label for="year">Year</label><input type="number" id="year" name="year" value="{{ now()->month === 12 ? now()->year : now()->year - 1 }}" required style="width:110px"></div>
            <button class="btn btn-primary">Post year-end</button>
        </form>
    </section>
</div>
<section class="card" style="margin-top:16px">
    <h2>Recent postings</h2>
    <table class="table"><tbody>
    @forelse ($recent as $r)
        <tr><td class="small num">{{ $r->created_at->format('Y-m-d H:i') }}</td><td>{{ $r->action === 'leave.month_posted' ? 'Month '.($r->details['period'] ?? '') : 'Year-end '.($r->details['year'] ?? '') }}</td><td class="small muted">{{ json_encode(\Illuminate\Support\Arr::except($r->details ?? [], ['period', 'year'])) }}</td></tr>
    @empty <tr><td class="muted">Nothing posted yet.</td></tr> @endforelse
    </tbody></table>
</section>
@endsection
