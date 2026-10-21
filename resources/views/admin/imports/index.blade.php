@extends('layouts.app')
@section('title', 'Import data')
@section('content')
<div class="page-head"><div><h1>Import data from CSV</h1><div class="muted">Bring records over from spreadsheets or your old system, section by section.</div></div></div>

<section class="card" style="margin-bottom:16px">
    <h2>How it works</h2>
    <ol class="small" style="margin:0;padding-left:20px;line-height:1.8">
        <li>Open a section below and <b>download its template</b> (a CSV file with the right column headers and sample rows).</li>
        <li>Fill it in with <b>Excel or Google Sheets</b>. Keep the first row (headers) as is; delete the sample rows.</li>
        <li>Save as <b>CSV UTF-8</b> (Excel: File → Save As → “CSV UTF-8 (Comma delimited)”; Google Sheets: File → Download → CSV).</li>
        <li><b>Upload</b> it. You'll see a preview with any problems per row — <b>nothing is saved until you click Import</b>.</li>
    </ol>
    <p class="hint" style="margin-bottom:0">Recommended order for a new setup: Plantilla items → Employees → Holidays → Beginning leave balances → the rest. Every import is recorded in the audit log.</p>
</section>

<div class="grid grid-3">
    @foreach ($importers as $key => $i)
        <a class="action-card" href="{{ route('admin.imports.show', $key) }}">
            <span class="ic"><x-icon name="list" size="20" /></span>
            <span><span class="t" style="display:block">{{ $loop->iteration }}. {{ $i->title() }}</span><span class="d">{{ \Illuminate\Support\Str::limit($i->description(), 110) }}</span></span>
        </a>
    @endforeach
</div>
@endsection
