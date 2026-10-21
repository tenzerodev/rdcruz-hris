@extends('layouts.app')
@section('title', 'Issued documents')
@section('content')
<div class="page-head">
    <div><h1>Issued documents</h1><div class="muted">Every printout gets a verification code. Revoke one if it was issued by mistake or replaced.</div></div>
    <a class="btn" href="{{ route('verify.form') }}" target="_blank"><x-icon name="shield" size="16" /> Open public verify page</a>
</div>
<section class="card">
    <form class="inline-form" method="get" style="margin-bottom:12px">
        <div class="field" style="flex:1;min-width:200px"><label for="q" class="sr">Search</label><input type="search" id="q" name="q" value="{{ $q }}" placeholder="Code or name"></div>
        <div class="field"><label for="type" class="sr">Type</label><select id="type" name="type"><option value="">All documents</option>@foreach (\App\Models\DocumentVerification::TYPES as $k => $l)<option value="{{ $k }}" @selected(request('type') === $k)>{{ $l }}</option>@endforeach</select></div>
        <button class="btn">Search</button>
    </form>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Code</th><th>Document</th><th>Issued</th><th class="num">Checks</th><th>Status</th><th></th></tr></thead><tbody>
        @forelse ($docs as $d)
            <tr>
                <td><code>{{ $d->display_code }}</code></td>
                <td>{{ $d->title }}<div class="small muted">{{ $d->type_name }}</div></td>
                <td class="small">{{ $d->created_at->format('M j, Y g:i A') }}<div class="muted">{{ $d->issuer?->name }}</div></td>
                <td class="num">{{ $d->views }}</td>
                <td>@if ($d->revoked_at)<span class="badge badge-error" title="{{ $d->revoked_reason }}">Revoked</span>@else<span class="badge badge-ok">Valid</span>@endif</td>
                <td><div class="actions">
                    <a class="btn btn-sm" href="{{ route('verify.show', $d->code) }}" target="_blank">View</a>
                    @unless ($d->revoked_at)
                        <form method="post" action="{{ route('admin.documents.revoke', $d) }}" onsubmit="const r = prompt('Reason for revoking'); if (!r) return false; this.reason.value = r;">@csrf<input type="hidden" name="reason"><button class="btn btn-sm btn-danger">Revoke</button></form>
                    @endunless
                </div></td>
            </tr>
        @empty <tr><td colspan="6" class="empty">No documents printed yet.</td></tr> @endforelse
        </tbody></table></div>
    <div class="pagination">{{ $docs->links() }}</div>
</section>
@endsection
