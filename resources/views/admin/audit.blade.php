@extends('layouts.app')
@section('title', 'Audit log')
@section('content')
<h1 style="margin-bottom:16px">Audit log</h1>
<div class="card table-wrap">
    <table class="table">
        <thead><tr><th>When</th><th>Who</th><th>Action</th><th>Subject</th><th>Details</th></tr></thead>
        <tbody>
        @forelse ($logs as $log)
            <tr>
                <td class="num small">{{ $log->created_at->format('Y-m-d H:i') }}</td>
                <td>{{ $log->user?->name ?? 'system' }}</td>
                <td><span class="badge">{{ $log->action }}</span></td>
                <td class="small">{{ $log->subject_type }} {{ $log->subject_id ? '#'.$log->subject_id : '' }}</td>
                <td class="small muted" style="max-width:420px;word-break:break-word">{{ $log->details ? json_encode($log->details, JSON_UNESCAPED_UNICODE) : '' }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="muted">Nothing yet.</td></tr>
        @endforelse
        </tbody>
    </table>
    <div class="pagination">{{ $logs->links() }}</div>
</div>
@endsection
