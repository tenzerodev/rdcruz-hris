@extends('layouts.app')
@section('title', 'Memos')
@section('content')
<div class="page-head"><div><h1>Memos &amp; announcements</h1><div class="muted">From HR and management.</div></div></div>
<section class="card">
    <div class="table-wrap"><table class="table"><tbody>
    @forelse ($items as $a)
        @php $r = $reads[$a->id] ?? null; @endphp
        <tr>
            <td><a href="{{ route('me.memos.show', $a) }}"><b>{{ $a->title }}</b></a>@if ($a->pinned) <span class="badge badge-info">Pinned</span>@endif
                <div class="small muted">{{ $a->kind_name }}{{ $a->reference_no ? ' '.$a->reference_no : '' }} · {{ $a->published_at->format('M j, Y') }}</div></td>
            <td class="small">@if ($r?->acknowledged_at)<span class="badge badge-ok">Acknowledged</span>@elseif ($a->requires_ack)<span class="badge badge-warn">Please read</span>@elseif (! $r)<span class="badge badge-info">New</span>@endif</td>
        </tr>
    @empty <tr><td class="empty">No memos right now.</td></tr> @endforelse
    </tbody></table></div>
</section>
@endsection
