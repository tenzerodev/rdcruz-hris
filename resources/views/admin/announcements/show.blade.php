@extends('layouts.app')
@section('title', $item->title)
@section('content')
@php $acked = $reads->whereNotNull('acknowledged_at')->count(); @endphp
<div class="page-head">
    <div><h1>{{ $item->title }}</h1><div class="muted">{{ $item->kind_name }}{{ $item->reference_no ? ' '.$item->reference_no : '' }} · {{ $item->audience_label }} ·
        @if ($item->published_at) published {{ $item->published_at->format('M j, Y g:i A') }} @else <span class="badge badge-warn">Draft</span> @endif</div></div>
    @unless ($readOnly)<div class="actions">
        <a class="btn" href="{{ route('admin.announcements.edit', $item) }}">Edit</a>
        @if ($item->published_at)
            <form method="post" action="{{ route('admin.announcements.unpublish', $item) }}" onsubmit="return confirm('Take it down? Employees will no longer see it.')">@csrf<button class="btn">Take down</button></form>
        @else
            <form method="post" action="{{ route('admin.announcements.publish', $item) }}" onsubmit="return confirm('Publish now? Everyone it is for will be notified.')">@csrf<button class="btn btn-primary">Publish</button></form>
        @endif
        <form method="post" action="{{ route('admin.announcements.destroy', $item) }}" onsubmit="return confirm('Delete it and its acknowledgments?')">@csrf @method('delete')<button class="btn btn-danger">Delete</button></form>
    </div>@endunless
</div>
<div class="grid" style="grid-template-columns: minmax(0, 3fr) minmax(280px, 2fr)">
    <section class="card prose">
        {!! \Illuminate\Support\Str::markdown($item->body, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
        @if ($item->attachment_path)<p><a class="btn btn-sm" href="{{ route('me.memos.attachment', $item) }}" target="_blank"><x-icon name="file" size="16" /> {{ $item->attachment_name }}</a></p>@endif
    </section>
    <section class="card">
        <div class="card-head"><h2>{{ $item->requires_ack ? 'Acknowledged' : 'Read' }}: {{ $item->requires_ack ? $acked : $reads->count() }} of {{ $recipients->count() }}</h2>
            <a class="btn btn-sm" href="{{ route('admin.announcements.show', [$item, 'format' => 'csv']) }}">CSV</a></div>
        @if ($recipients->count())<div class="meter" style="margin-bottom:12px"><span style="width:{{ round(($item->requires_ack ? $acked : $reads->count()) / max(1, $recipients->count()) * 100) }}%;background:var(--primary)"></span></div>@endif
        <div class="table-wrap" style="max-height:520px;overflow:auto"><table class="table"><tbody>
            @foreach ($recipients->sortBy(fn ($e) => ($reads->get($e->id)?->acknowledged_at ? 1 : 0).$e->last_name) as $e)
                @php $r = $reads->get($e->id); @endphp
                <tr><td>{{ $e->full_name }}<div class="small muted">{{ $e->office }}</div></td>
                    <td class="small">@if ($r?->acknowledged_at)<span class="badge badge-ok">Acknowledged</span> {{ $r->acknowledged_at->format('M j, g:i A') }}@elseif ($r)<span class="badge badge-info">Read</span>@else<span class="badge">Not yet</span>@endif</td></tr>
            @endforeach
        </tbody></table></div>
    </section>
</div>
@endsection
