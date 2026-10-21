@extends('layouts.app')
@section('title', 'Preview import')
@section('content')
@php
    $valid = count($data['valid']);
    $bad = count($data['errors']);
    $cols = array_slice($importer->columns(), 0, 6);
@endphp
<div class="page-head">
    <div><h1>Check before importing</h1><div class="muted">{{ $importer->title() }} · {{ $data['file'] }} · {{ $data['total'] }} row(s)</div></div>
</div>

<div class="grid grid-3" style="margin-bottom:16px">
    <div class="action-card {{ $valid ? '' : 'clear' }}"><span class="ic" style="background:var(--ok-soft);color:var(--ok)"><x-icon name="check" size="20" /></span><span><span class="n">{{ $valid }}</span><span class="t" style="display:block">Ready to import</span></span></div>
    <div class="action-card {{ $bad ? 'hot' : 'clear' }}"><span class="ic"><x-icon name="alert" size="20" /></span><span><span class="n">{{ $bad }}</span><span class="t" style="display:block">Rows with problems</span><span class="d">{{ $bad ? 'Will be skipped' : 'None' }}</span></span></div>
    <div class="card" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
        @if ($valid)
            <form method="post" action="{{ route('admin.imports.run', [$importer->key(), $token]) }}" onsubmit="this.querySelector('button').disabled = true">@csrf
                <button class="btn btn-primary">Import {{ $valid }} row(s)</button></form>
        @endif
        <form method="post" action="{{ route('admin.imports.cancel', [$importer->key(), $token]) }}">@csrf @method('delete')<button class="btn">Cancel</button></form>
    </div>
</div>

@if ($data['unknown'])
    <div class="alert alert-warn"><x-icon name="alert" /><div>These columns aren't used and will be ignored: <b>{{ implode(', ', $data['unknown']) }}</b>. Check for typos in the header row.</div></div>
@endif

@if ($bad)
<section class="card" style="margin-bottom:16px">
    <h2>Problems to fix</h2>
    <p class="small muted">Line numbers match the rows in your spreadsheet (line 1 is the header). You can import the good rows now and upload the fixed rows later.</p>
    <div class="table-wrap"><table class="table"><thead><tr><th style="width:80px">Line</th><th>Problem</th></tr></thead><tbody>
        @foreach (array_slice($data['errors'], 0, 200, true) as $line => $msgs)
            <tr><td class="num">{{ $line }}</td><td class="small">@foreach ($msgs as $m)<div>{{ $m }}</div>@endforeach</td></tr>
        @endforeach
    </tbody></table></div>
    @if ($bad > 200)<p class="small muted">…and {{ $bad - 200 }} more.</p>@endif
</section>
@endif

@if ($valid)
<section class="card">
    <h2>First rows to be imported</h2>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Line</th>@foreach ($cols as $c)<th>{{ $c['label'] }}</th>@endforeach</tr></thead><tbody>
        @foreach (array_slice($data['valid'], 0, 15) as $row)
            <tr><td class="num">{{ $row['_line'] }}</td>@foreach ($cols as $c)<td class="small">{{ $row[$c['name']] }}</td>@endforeach</tr>
        @endforeach
        </tbody></table></div>
    @if ($valid > 15)<p class="small muted">…and {{ $valid - 15 }} more.</p>@endif
</section>
@endif
@endsection
