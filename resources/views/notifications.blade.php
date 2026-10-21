@extends('layouts.app')
@section('title', 'Notifications')
@section('content')
<div class="page-head">
    <div><h1>Notifications</h1><div class="muted">Requests, approvals, payslips, memos and reminders for you.</div></div>
    <div class="actions">
        @if (auth()->user()->unreadNotifications()->exists())
            <form method="post" action="{{ route('notifications.read-all') }}">@csrf<button class="btn">Mark all as read</button></form>
        @endif
    </div>
</div>
<section class="card">
    <div class="table-wrap"><table class="table notices"><tbody>
    @forelse ($notifications as $n)
        <tr @class(['unread' => ! $n->read_at])>
            <td style="width:14px">@unless ($n->read_at)<span class="dot" aria-label="Unread"></span>@endunless</td>
            <td><a href="{{ route('notifications.open', $n->id) }}"><b>{{ $n->data['subject'] ?? 'Notice' }}</b></a>
                @foreach (array_slice($n->data['lines'] ?? [], 0, 3) as $line)<div class="small muted">{{ $line }}</div>@endforeach</td>
            <td class="small muted" style="white-space:nowrap">{{ $n->created_at->diffForHumans() }}</td>
        </tr>
    @empty <tr><td class="empty">No notifications yet.</td></tr> @endforelse
    </tbody></table></div>
    <div class="pagination">{{ $notifications->links() }}</div>
</section>
<section class="card">
    <h2>Email</h2>
    <form method="post" action="{{ route('notifications.preferences') }}" class="inline-form">@csrf
        <label class="check"><input type="checkbox" name="notify_email" value="1" @checked(auth()->user()->notify_email) onchange="this.form.submit()"> Also send me these by email{{ auth()->user()->routeNotificationForMail() ? ' ('.auth()->user()->routeNotificationForMail().')' : '' }}</label>
        <noscript><button class="btn btn-sm">Save</button></noscript>
    </form>
    @unless (auth()->user()->routeNotificationForMail())<p class="hint">No email address is on your record. Ask HR to add one.</p>@endunless
</section>
@endsection
