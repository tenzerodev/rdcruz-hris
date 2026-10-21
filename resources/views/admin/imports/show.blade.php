@extends('layouts.app')
@section('title', 'Import '.$importer->title())
@section('content')
<div class="page-head">
    <div><h1>Import: {{ $importer->title() }}</h1><div class="muted">{{ $importer->description() }}</div></div>
    <div class="actions"><a class="btn" href="{{ route('admin.imports.index') }}">All imports</a>@if ($importer->sectionRoute() !== 'admin.imports.index')<a class="btn" href="{{ route($importer->sectionRoute()) }}">Go to section</a>@endif</div>
</div>

@if ($result)
    <div class="alert alert-ok" role="status"><x-icon name="check" /><div>
        <b>Imported {{ $result['file'] }}:</b> {{ $result['created'] }} added, {{ $result['updated'] }} updated{{ $result['skipped'] ? ', '.$result['skipped'].' already there (skipped)' : '' }}.
        @if ($result['not_imported'])<br>{{ $result['not_imported'] }} row(s) with problems were not imported — fix them and upload just those rows again.@endif
    </div></div>
@endif

<div class="grid" style="grid-template-columns: minmax(0, 1fr) minmax(280px, 360px); align-items:start">
    <section class="card">
        <div class="card-head"><h2>Columns</h2><a class="btn btn-sm" href="{{ route('admin.imports.template', $importer->key()) }}"><x-icon name="file" size="16" /> Download template</a></div>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Column header</th><th>What to enter</th><th>Example</th></tr></thead><tbody>
            @foreach ($importer->columns() as $c)
                <tr>
                    <td><code>{{ $c['name'] }}</code>@if ($c['required'] ?? false) <span class="badge badge-warn">required</span>@endif<div class="small muted">{{ $c['label'] }}</div></td>
                    <td class="small">{{ $c['format'] }}</td>
                    <td class="small"><code>{{ $c['example'] }}</code></td>
                </tr>
            @endforeach
            </tbody></table></div>
    </section>

    <div class="stack">
        <section class="card">
            <h2>Upload</h2>
            <form method="post" action="{{ route('admin.imports.upload', $importer->key()) }}" enctype="multipart/form-data">@csrf
                <div class="field"><label for="file">CSV file (up to 5 MB, 5,000 rows)</label><input type="file" id="file" name="file" accept=".csv,text/csv" required></div>
                <button class="btn btn-primary" style="width:100%">Upload and preview</button>
            </form>
            <p class="hint" style="margin-bottom:0">Nothing is saved yet — you'll review the rows first.</p>
        </section>
        <section class="card">
            <h2>Tips</h2>
            <ul class="small" style="margin:0;padding-left:18px;line-height:1.7">
                <li>Keep the header row exactly as in the template. Column order doesn't matter; extra columns are ignored.</li>
                <li>Dates: <code>2026-10-05</code> or <code>10/05/2026</code> (month/day/year). Times: <code>08:05</code> or <code>5:30 PM</code>.</li>
                <li>Numbers without ₱ or commas are safest (<code>52000</code>).</li>
                <li>Leave a cell blank if you don't have the information.</li>
                @foreach ($importer->notes() as $note)<li>{{ $note }}</li>@endforeach
            </ul>
        </section>
    </div>
</div>
@endsection
