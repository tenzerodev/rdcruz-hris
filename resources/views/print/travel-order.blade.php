@extends('print._layout')
@section('title', $order->type_name.' — '.$order->destination)
@section('content')
<x-print-header />
<h1>{{ mb_strtoupper($order->type_name) }}</h1>
<p class="c small">No. {{ $order->reference_no ?: 'TO-'.str_pad($order->id, 6, '0', STR_PAD_LEFT) }} · {{ $order->approved_at?->format('F j, Y') }}</p>
<table class="f" style="margin-top:6mm">
    <tr><td style="width:30%"><div class="lbl">Personnel</div></td><td>@foreach ($order->employees as $e)<div class="val">{{ $e->display_name }} <span class="small" style="font-weight:normal">— {{ $e->position }}, {{ $e->office }}</span></div>@endforeach</td></tr>
    <tr><td><div class="lbl">Inclusive dates</div></td><td class="val">{{ $order->date_from->format('F j, Y') }}{{ $order->date_to->ne($order->date_from) ? ' – '.$order->date_to->format('F j, Y') : '' }}{{ $order->time_from ? ', '.$order->time_from.'–'.$order->time_to : '' }}</td></tr>
    <tr><td><div class="lbl">Destination / venue</div></td><td class="val">{{ $order->destination }}</td></tr>
    <tr><td><div class="lbl">Purpose</div></td><td class="val">{{ $order->purpose }}</td></tr>
</table>
<p style="margin-top:6mm">The above-named personnel are hereby authorized to proceed as stated. A certificate of appearance or report should be submitted upon return.</p>
<div style="margin-top:18mm;margin-left:55%" class="sign"><div class="who">{{ $order->approver?->name }}</div><span class="n">Approved by</span></div>
<x-verify-stamp type="travel_order" :key="'travel:'.$order->id" :title="$order->type_name.' — '.$order->destination" :summary="[
    'Personnel' => $order->employees->map->full_name->join('; '), 'Destination' => $order->destination, 'Purpose' => $order->purpose,
    'Dates' => $order->date_from->format('M j, Y').($order->date_to->ne($order->date_from) ? ' – '.$order->date_to->format('M j, Y') : '').($order->time_from ? ', '.$order->time_from.'–'.$order->time_to : ''),
    'Status' => ucfirst($order->status),
]" style="margin-top:20mm" />
@endsection
