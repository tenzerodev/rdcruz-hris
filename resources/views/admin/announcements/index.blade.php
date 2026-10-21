@extends('layouts.app')
@section('title', 'Memos & announcements')
@section('content')
<div class="page-head">
    <div><h1>Memos &amp; announcements</h1><div class="muted">Post memos, office orders and circulars. Employees see them on their home page and click “I have read this”.</div></div>
    @unless ($readOnly)<a class="btn btn-primary" href="{{ route('admin.announcements.create') }}"><x-icon name="plus" size="16" /> New</a>@endunless
</div>
<section class="card">
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Title</th><th>For</th><th>Published</th><th class="num">Acknowledged</th><th></th></tr></thead>
        <tbody>
        @forelse ($items as $a)
            <tr>
                <td><a href="{{ route('admin.announcements.show', $a) }}"><b>{{ $a->title }}</b></a>@if ($a->pinned) <span class="badge badge-info">Pinned</span>@endif
                    <div class="small muted">{{ $a->kind_name }}{{ $a->reference_no ? ' '.$a->reference_no : '' }}</div></td>
                <td class="small">{{ $a->audience_label }}</td>
                <td class="small">@if ($a->published_at){{ $a->published_at->format('M j, Y') }}@if ($a->expires_on && $a->expires_on->isPast())<div class="muted">Expired</div>@endif @else<span class="badge badge-warn">Draft</span>@endif</td>
                <td class="num">@if ($a->published_at && $a->requires_ack){{ $a->acknowledged_count }} / {{ $a->recipients()->count() }}@else — @endif</td>
                <td><a class="btn btn-sm" href="{{ route('admin.announcements.show', $a) }}">Open</a></td>
            </tr>
        @empty <tr><td colspan="5" class="empty">Nothing posted yet.</td></tr> @endforelse
        </tbody>
    </table></div>
    <div class="pagination">{{ $items->links() }}</div>
</section>
@endsection
