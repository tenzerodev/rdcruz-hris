{{--
    Issues (or reuses) a verification code for this printout and shows the QR + code.
    Same content printed again keeps its code; changed content gets a new one.
--}}
@props(['type', 'key', 'title', 'summary' => [], 'employee' => null, 'compact' => false])
@php
    $verifier = app(\App\Services\DocumentVerifier::class);
    $doc = $verifier->issue($type, $key, $title, $summary, $employee);
@endphp
<div {{ $attributes->merge(['class' => 'vs'.($compact ? ' vs-compact' : '')]) }}>
    <div class="vs-qr">{!! $verifier->qr($doc) !!}</div>
    <div class="vs-text">
        <b>Verify this document</b>
        <div>Scan the code or visit <span class="vs-url">{{ rtrim(config('app.url'), '/') }}/verify</span></div>
        <div>Code: <span class="vs-code">{{ $doc->display_code }}</span></div>
    </div>
</div>
@once
<style>
    .vs { display: flex; align-items: center; gap: 3mm; font: 7pt/1.3 Arial, Helvetica, sans-serif; color: #000; margin-top: 3mm; break-inside: avoid; }
    .vs-qr { width: 20mm; height: 20mm; flex: none; } .vs-qr svg { width: 100%; height: 100%; display: block; }
    .vs-compact .vs-qr { width: 14mm; height: 14mm; }
    .vs-code { font-family: "Courier New", monospace; font-weight: bold; font-size: 8.5pt; letter-spacing: .5px; }
    .vs-url { font-weight: bold; }
    .vs-compact { font-size: 6.2pt; margin-top: 1.5mm; } .vs-compact .vs-code { font-size: 7pt; }
</style>
@endonce
