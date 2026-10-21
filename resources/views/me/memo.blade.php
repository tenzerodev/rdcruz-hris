@extends('layouts.app')
@section('title', $item->title)
@section('content')
<div class="page-head"><div><h1>{{ $item->title }}</h1><div class="muted">{{ $item->kind_name }}{{ $item->reference_no ? ' '.$item->reference_no : '' }} · {{ $item->published_at->format('F j, Y') }}</div></div>
    <a class="btn" href="{{ route('me.memos') }}">All memos</a></div>
<section class="card prose">
    {!! \Illuminate\Support\Str::markdown($item->body, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
    @if ($item->attachment_path)<p><a class="btn btn-sm" href="{{ route('me.memos.attachment', $item) }}" target="_blank"><x-icon name="file" size="16" /> {{ $item->attachment_name }}</a></p>@endif
</section>
@if ($item->requires_ack)
<section class="card">
    @if ($read->acknowledged_at)
        <p style="margin:0"><x-icon name="check" /> You acknowledged this on {{ $read->acknowledged_at->format('F j, Y g:i A') }}.</p>
    @else
        <form method="post" action="{{ route('me.memos.acknowledge', $item) }}">@csrf
            <button class="btn btn-primary"><x-icon name="check" size="16" /> I have read and understood this</button>
        </form>
    @endif
</section>
@endif
@endsection
