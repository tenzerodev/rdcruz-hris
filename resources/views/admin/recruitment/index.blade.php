@extends('layouts.app')
@section('title', 'Recruitment')
@section('content')
<div class="page-head">
    <div><h1>Recruitment &amp; selection</h1><div class="muted">Publish vacancies, receive applications online, score them with the HRMPSB, and appoint.</div></div>
    <div class="actions">
        <a class="btn" href="{{ route('careers.index') }}" target="_blank">Careers page</a>
        <a class="btn" href="{{ route('admin.recruitment.publication') }}" target="_blank">CS Form 9 (open vacancies)</a>
        @unless ($readOnly)<a class="btn btn-primary" href="{{ route('admin.recruitment.create') }}"><x-icon name="plus" size="16" /> New vacancy</a>@endunless
    </div>
</div>
<nav class="tabs">
    @foreach (['active' => 'In progress', 'filled' => 'Filled', 'cancelled' => 'Cancelled', 'all' => 'All'] as $k => $l)
        <a href="{{ route('admin.recruitment.index', ['status' => $k]) }}" @if ($status === $k) aria-current="page" @endif>{{ $l }}</a>
    @endforeach
</nav>
<div class="grid" style="grid-template-columns: minmax(0, 2fr) minmax(260px, 1fr)">
    <section class="card">
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Position</th><th>Posting</th><th class="num">Applicants</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse ($vacancies as $v)
                <tr>
                    <td><a href="{{ route('admin.recruitment.show', $v) }}"><b>{{ $v->title }}</b></a><div class="small muted">{{ $v->item_no ? 'Item '.$v->item_no.' · ' : '' }}{{ $v->type_label }}{{ $v->salary_grade ? ' · SG '.$v->salary_grade : '' }}{{ $v->place_of_assignment ? ' · '.$v->place_of_assignment : '' }}</div></td>
                    <td class="small">{{ $v->posted_on?->format('M j') ?? '—' }} – {{ $v->closing_on?->format('M j, Y') ?? 'open' }}@unless ($v->public)<div class="muted">Internal only</div>@endunless</td>
                    <td class="num">{{ $v->applicants_count }}</td>
                    <td>@include('admin.recruitment._status', ['status' => $v->status, 'label' => \App\Models\Vacancy::STATUSES[$v->status]])</td>
                    <td><a class="btn btn-sm" href="{{ route('admin.recruitment.show', $v) }}">Open</a></td>
                </tr>
            @empty <tr><td colspan="5" class="empty">No vacancies here.</td></tr> @endforelse
            </tbody>
        </table></div>
        <div class="pagination">{{ $vacancies->links() }}</div>
    </section>
    <section class="card">
        <h2>Vacant plantilla items</h2>
        <table class="table"><tbody>
        @forelse ($vacant as $p)
            <tr><td><b>{{ $p->title }}</b><div class="small muted">Item {{ $p->item_no }}{{ $p->salary_grade ? ' · SG '.$p->salary_grade : '' }}{{ $p->office ? ' · '.$p->office : '' }}</div></td>
                <td style="width:1%">@unless ($readOnly)<a class="btn btn-sm" href="{{ route('admin.recruitment.create', ['position' => $p->id]) }}">Post</a>@endunless</td></tr>
        @empty <tr><td class="muted">Every active item has an incumbent or a vacancy in progress.</td></tr> @endforelse
        </tbody></table>
    </section>
</div>
@endsection
