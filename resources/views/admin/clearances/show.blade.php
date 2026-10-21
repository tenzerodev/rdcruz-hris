@extends('layouts.app')
@section('title', 'Clearance — '.$c->employee->full_name)
@section('content')
@php $e = $c->employee; $open = $c->status === 'open'; @endphp
<div class="page-head">
    <div><h1>Clearance — {{ $e->full_name }}</h1>
        <div class="muted">{{ $e->position }} · {{ $e->office }} · separation {{ $c->separation_date->format('F j, Y') }} ({{ $c->reason }}) ·
            <span class="badge {{ ['open' => 'badge-warn', 'completed' => 'badge-ok', 'cancelled' => ''][$c->status] }}">{{ ucfirst($c->status) }}</span></div></div>
    <div class="actions"><a class="btn" href="{{ route('admin.clearances.print', $c) }}" target="_blank">Print clearance</a><a class="btn" href="{{ route('admin.clearances.index') }}">Back</a></div>
</div>
<section class="card" style="margin-bottom:16px">
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Office</th><th>Checks</th><th>Cleared</th><th></th></tr></thead><tbody>
        @foreach ($c->items as $i)
            <tr><td><b>{{ $i->office }}</b></td><td class="small">{{ $i->checks }}</td>
                <td>@if ($i->cleared_at)<span class="badge badge-ok">Cleared</span><div class="small">{{ $i->cleared_by }} · {{ $i->cleared_at->format('M j, Y') }}</div>@if ($i->remarks)<div class="small muted">{{ $i->remarks }}</div>@endif
                    @else<span class="badge badge-warn">Pending</span>@endif</td>
                <td>@if ($open && ! $readOnly)
                    @if ($i->cleared_at)
                        <form method="post" action="{{ route('admin.clearances.unsign', $i) }}">@csrf<button class="btn btn-sm">Undo</button></form>
                    @else
                        <form method="post" action="{{ route('admin.clearances.sign', $i) }}" class="inline-form">@csrf
                            <div class="field"><label class="sr" for="by{{ $i->id }}">Cleared by</label><input type="text" id="by{{ $i->id }}" name="cleared_by" placeholder="Name of signatory" required style="width:170px"></div>
                            <div class="field"><label class="sr" for="rm{{ $i->id }}">Remarks</label><input type="text" id="rm{{ $i->id }}" name="remarks" placeholder="Remarks" style="width:140px"></div>
                            <button class="btn btn-sm btn-primary">Clear</button></form>
                    @endif
                @endif</td></tr>
        @endforeach
        </tbody></table></div>
</section>
@if ($open && ! $readOnly)
    <section class="card">
        <h2>Complete</h2>
        <p>When every office has cleared, completing the clearance will:</p>
        <ul class="small">
            <li>mark {{ $e->first_name }} separated on {{ $c->separation_date->format('M j, Y') }} and close the service record</li>
            <li>disable their HRIS login{{ $e->issuedDevices->isNotEmpty() ? ' and issued computers ('.$e->issuedDevices->pluck('name')->join(', ').')' : '' }}</li>
            <li>end any work-from-home arrangement on that date</li>
        </ul>
        <div class="actions" style="justify-content:flex-start">
            <form method="post" action="{{ route('admin.clearances.complete', $c) }}" onsubmit="return confirm('Complete the clearance and separate {{ $e->full_name }}?')">@csrf<button class="btn btn-primary" @disabled(! $c->isDone())>Complete clearance</button></form>
            <form method="post" action="{{ route('admin.clearances.cancel', $c) }}" onsubmit="return confirm('Cancel this clearance?')">@csrf<button class="btn">Cancel clearance</button></form>
        </div>
        <p class="hint">Afterwards: <a href="{{ route('admin.leave.monetization', ['employee_id' => $e->id]) }}">terminal leave benefit</a>, last salary and the certificate of employment / service record.</p>
    </section>
@endif
@endsection
