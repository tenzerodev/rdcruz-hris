@extends($print ? 'print._layout' : 'layouts.app')
@section('title', 'Plantilla of Personnel')
@section('content')
@php $money = fn ($v) => $v ? number_format((float) $v, 2) : ''; @endphp
@if ($print)
    <x-print-header /><h1>PLANTILLA OF PERSONNEL</h1><p class="c small">As of {{ now()->format('F j, Y') }}</p>
@else
<div class="page-head">
    <div><h1>Plantilla of Personnel</h1><div class="muted">Every active item with its incumbent, or vacant. {{ $positions->filter(fn ($p) => ! $p->employee)->count() }} of {{ $positions->count() }} vacant.</div></div>
    <a class="btn" href="{{ request()->fullUrlWithQuery(['print' => 1]) }}" target="_blank">Print</a>
</div>
@endif
<section @class(['card' => ! $print])>
    <div class="table-wrap"><table @class(['table' => ! $print, 'f' => $print])>
        <thead><tr><th>Item no.</th><th>Position title</th><th>SG</th><th>Incumbent</th><th>Step</th><th class="num">Annual salary</th><th>Date of original appointment</th><th>Office</th></tr></thead>
        <tbody>
        @forelse ($positions as $p)
            @php $e = $p->employee; @endphp
            <tr><td>{{ $p->item_no }}</td><td>{{ $p->title }}</td><td>{{ $p->salary_grade }}</td>
                <td>{!! $e ? e($e->display_name) : '<i>VACANT</i>' !!}</td><td>{{ $e?->step }}</td><td class="num">{{ $money($e?->monthly_salary ? $e->monthly_salary * 12 : null) }}</td>
                <td>{{ $e?->date_hired?->format('m/d/Y') }}</td><td>{{ $p->office }}</td></tr>
        @empty <tr><td colspan="8" class="c">No plantilla items.</td></tr> @endforelse
        </tbody></table></div>
</section>
@endsection
