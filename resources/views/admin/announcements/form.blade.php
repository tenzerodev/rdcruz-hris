@extends('layouts.app')
@section('title', $item->exists ? 'Edit '.$item->title : 'New memo')
@section('content')
@php $aud = $item->audience ?? []; @endphp
<div class="page-head"><div><h1>{{ $item->exists ? 'Edit' : 'New memo or announcement' }}</h1></div>
    <a class="btn" href="{{ $item->exists ? route('admin.announcements.show', $item) : route('admin.announcements.index') }}">Back</a></div>
<form method="post" enctype="multipart/form-data" action="{{ $item->exists ? route('admin.announcements.update', $item) : route('admin.announcements.store') }}">
    @csrf @if ($item->exists) @method('put') @endif
    <div class="grid" style="grid-template-columns: minmax(0, 2fr) minmax(260px, 1fr)">
        <section class="card">
            <div class="row">
                <div class="field"><label for="kind">Kind</label><select id="kind" name="kind">@foreach (\App\Models\Announcement::KINDS as $k => $l)<option value="{{ $k }}" @selected(old('kind', $item->kind) === $k)>{{ $l }}</option>@endforeach</select></div>
                <div class="field"><label for="reference_no">Reference no.</label><input type="text" id="reference_no" name="reference_no" value="{{ old('reference_no', $item->reference_no) }}" placeholder="e.g. Memo No. 2026-045"></div>
            </div>
            <div class="field"><label for="title">Title / subject</label><input type="text" id="title" name="title" value="{{ old('title', $item->title) }}" required maxlength="200"></div>
            <div class="field"><label for="body">Message</label><textarea id="body" name="body" rows="14" required>{{ old('body', $item->body) }}</textarea>
                <div class="hint">Plain text. Blank lines start a new paragraph; **bold**, lists starting with “- ” and links work.</div></div>
            <div class="field"><label for="attachment">Attach the signed copy (PDF or image, up to 10 MB)</label><input type="file" id="attachment" name="attachment" accept=".pdf,.jpg,.jpeg,.png">
                @if ($item->attachment_name)<label class="check small"><input type="checkbox" name="remove_attachment" value="1"> Remove {{ $item->attachment_name }}</label>@endif</div>
        </section>
        <section class="card">
            <h2>Who it's for</h2>
            <div class="field"><label for="offices">Offices</label><select id="offices" name="offices[]" multiple size="6">@foreach ($offices as $o)<option value="{{ $o }}" @selected(in_array($o, old('offices', $aud['offices'] ?? []), true))>{{ $o }}</option>@endforeach</select>
                <div class="hint">None selected = all offices. Hold Ctrl/⌘ to pick several.</div></div>
            <div class="field"><label>Employee groups</label>
                @foreach (config('hris.groups') as $k => $l)<label class="check"><input type="checkbox" name="groups[]" value="{{ $k }}" @checked(in_array($k, old('groups', $aud['groups'] ?? []), true))> {{ $l }}</label>@endforeach
                <div class="hint">None ticked = everyone.</div></div>
            <label class="check"><input type="checkbox" name="requires_ack" value="1" @checked(old('requires_ack', $item->requires_ack))> Ask employees to acknowledge (“I have read this”)</label>
            <label class="check"><input type="checkbox" name="pinned" value="1" @checked(old('pinned', $item->pinned))> Pin to the top</label>
            <div class="field"><label for="expires_on">Show until</label><input type="date" id="expires_on" name="expires_on" value="{{ old('expires_on', $item->expires_on?->toDateString()) }}"><div class="hint">Optional.</div></div>
            @unless ($item->published_at)
                <label class="check"><input type="checkbox" name="email" value="1"> Also email it when published</label>
            @endunless
            <div class="actions" style="margin-top:12px">
                <button class="btn">Save draft</button>
                @unless ($item->published_at)<button class="btn btn-primary" name="publish" value="1" onclick="return confirm('Publish now? Everyone it is for will be notified.')">Save &amp; publish</button>@else<button class="btn btn-primary">Save</button>@endunless
            </div>
        </section>
    </div>
</form>
@endsection
