@extends('layouts.app')
@section('title', 'Verify a document')
@section('bare', true)
@section('main-class', 'auth')
@section('content')
@php $logo = \App\Models\Setting::logoUrl(); @endphp
<div class="card" style="max-width:560px">
    <div class="brand" style="margin-bottom:16px">
        <span class="logo" aria-hidden="true">@if ($logo)<img src="{{ $logo }}" alt="">@else HR @endif</span>
        <span><b>Document verification</b><small>{{ \App\Models\Setting::get('agency_name') }}</small></span>
    </div>

    @if ($searched && $doc)
        @php
            $state = $doc->revoked_at ? 'revoked' : ($newer ? 'superseded' : 'valid');
            $tone = ['valid' => 'ok', 'superseded' => 'warn', 'revoked' => 'error'][$state];
        @endphp
        <div class="alert alert-{{ $tone }}" role="status">
            <x-icon :name="$state === 'valid' ? 'check' : 'alert'" />
            <div>
                @if ($state === 'valid')
                    <b>Authentic document.</b> It was issued by {{ \App\Models\Setting::get('agency_name') }} and is the current version.
                @elseif ($state === 'superseded')
                    <b>Authentic, but outdated.</b> A newer version of this document was issued on {{ $newer->created_at->format('F j, Y') }}. Ask the holder for the latest copy.
                @else
                    <b>Not valid.</b> This document was revoked on {{ $doc->revoked_at->format('F j, Y') }}{{ $doc->revoked_reason ? ': '.$doc->revoked_reason : '' }}.
                @endif
            </div>
        </div>
        <p class="small muted">Compare these details with the paper. If anything differs, the paper copy may have been altered.</p>
        <dl class="kv">
            <dt>Document</dt><dd><b>{{ $doc->type_name }}</b></dd>
            <dt>Title</dt><dd>{{ $doc->title }}</dd>
            @foreach ($doc->summary as $label => $value)
                <dt>{{ $label }}</dt><dd>{{ $value }}</dd>
            @endforeach
            <dt>Issued</dt><dd>{{ $doc->created_at->format('F j, Y g:i A') }}</dd>
            <dt>Code</dt><dd><code>{{ $doc->display_code }}</code></dd>
        </dl>
    @elseif ($searched)
        <div class="alert alert-error" role="alert"><x-icon name="alert" /><div><b>No document found</b> with code <code>{{ $code }}</code>. Check the code and try again. A document without a valid code should be treated as not genuine.</div></div>
    @else
        <p class="muted">Enter the code printed at the bottom of the document, or scan its QR code.</p>
    @endif

    <form method="get" action="{{ route('verify.form') }}" class="inline-form" style="margin-top:18px">
        <div class="field" style="flex:1;min-width:200px"><label for="code">Verification code</label><input type="text" id="code" name="code" placeholder="XXXX-XXXX-XXXX" autocomplete="off" required style="text-transform:uppercase"></div>
        <button class="btn btn-primary">Verify</button>
    </form>
</div>
@endsection
