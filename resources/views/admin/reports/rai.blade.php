@extends($print ? 'print._layout' : 'layouts.app')
@section('title', 'RAI '.$month->format('F Y'))
@section('content')
@if ($print)
    <x-print-header /><h1>REPORT ON APPOINTMENTS ISSUED (RAI)</h1><p class="c small">For the month of {{ $month->format('F Y') }}</p>
@else
<div class="page-head">
    <div><h1>Report on Appointments Issued — {{ $month->format('F Y') }}</h1><div class="muted">Appointments taking effect in the month, from the service records of plantilla personnel (step increments and salary adjustments are left out).</div></div>
    <div class="actions">
        <form class="inline-form" method="get"><div class="field"><label for="month" class="sr">Month</label><input type="month" id="month" name="month" value="{{ $month->format('Y-m') }}"></div><button class="btn">Show</button></form>
        <a class="btn" href="{{ request()->fullUrlWithQuery(['print' => 1]) }}" target="_blank">Print RAI</a>
    </div>
</div>
@endif
<section @class(['card' => ! $print])>
    <div class="table-wrap"><table @class(['table' => ! $print, 'f' => $print])>
        <thead><tr><th>Name</th><th>Position title</th><th>Item no.</th><th>SG</th><th>Status</th><th>Effectivity</th><th>Nature</th></tr></thead>
        <tbody>
        @forelse ($rows as $r)
            <tr><td>{{ $r['employee']->display_name }}</td><td>{{ $r['record']->designation }}</td><td>{{ $r['employee']->plantillaPosition?->item_no }}</td>
                <td>{{ $r['employee']->salary_grade }}</td><td>{{ $r['record']->status }}</td><td>{{ $r['record']->date_from->format('m/d/Y') }}</td><td>{{ $r['nature'] }}</td></tr>
        @empty <tr><td colspan="7" class="c">No appointments in {{ $month->format('F Y') }}.</td></tr> @endforelse
        </tbody></table></div>
    @if ($print)
        <div style="display:flex;gap:12mm;margin-top:16mm">
            <div class="sign" style="flex:1"><div class="who">{{ $settings['hr_head_name'] ?? '' }}</div><span class="n">{{ $settings['hr_head_title'] ?? 'HRMO' }}</span></div>
            <div class="sign" style="flex:1"><div class="who">{{ $settings['agency_head_name'] ?? '' }}</div><span class="n">{{ $settings['agency_head_title'] ?? 'Head of Agency' }}</span></div>
        </div>
    @else
        <p class="hint">Use the service record "Remarks" for the nature of appointment (e.g. Original, Promotion, Transfer, Reemployment); blank shows Original for a first record. Submit to the CSC field office on its required form; this list has the data.</p>
    @endif
</section>
@endsection
