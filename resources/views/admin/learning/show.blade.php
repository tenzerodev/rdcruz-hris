@extends('layouts.app')
@section('title', $program->title)
@section('content')
<div class="page-head">
    <div><h1>{{ $program->title }}</h1><div class="muted">{{ $program->year }} L&amp;D plan · {{ $program->when_label }}{{ $program->hours ? ' · '.$program->hours.' hours' : '' }} · {{ \App\Models\TrainingProgram::MODES[$program->mode] }}{{ $program->provider ? ' · '.$program->provider : '' }}
        · <span @class(['badge', 'badge-ok' => $program->status === 'done', 'badge-info' => $program->status === 'scheduled'])>{{ \App\Models\TrainingProgram::STATUSES[$program->status] }}</span></div></div>
    <a class="btn" href="{{ route('admin.learning.index', ['year' => $program->year]) }}">Back to plan</a>
</div>
<div class="grid" style="grid-template-columns: minmax(0, 3fr) minmax(280px, 2fr)">
    <section class="card">
        <h2>Participants ({{ $program->participants->count() }}{{ $program->slots ? ' of '.$program->slots.' slots' : '' }})</h2>
        @if ($program->status === 'done')
            <div class="table-wrap"><table class="table"><tbody>
            @foreach ($program->participants as $p)
                <tr><td>{{ $p->employee->full_name }}<div class="small muted">{{ $p->employee->office }}</div></td><td><span @class(['badge', 'badge-ok' => $p->status === 'attended'])>{{ \App\Models\TrainingParticipant::STATUSES[$p->status] }}</span></td></tr>
            @endforeach
            </tbody></table></div>
        @else
        <form method="post" action="{{ route('admin.learning.complete', $program) }}" id="complete">@csrf
            <div class="table-wrap"><table class="table"><tbody>
            @forelse ($program->participants as $p)
                <tr><td><label class="check"><input type="checkbox" name="attended[]" value="{{ $p->id }}" checked @disabled($readOnly)> {{ $p->employee->full_name }}</label><div class="small muted">{{ $p->employee->office }}</div></td>
                    <td>@unless ($readOnly)<button class="btn btn-sm" form="rm{{ $p->id }}">Remove</button>@endunless</td></tr>
            @empty <tr><td class="empty">No one nominated yet.</td></tr> @endforelse
            </tbody></table></div>
            @if (! $readOnly && $program->participants->isNotEmpty())
            <h3 style="margin:16px 0 6px">Mark as completed</h3>
            <p class="hint">Tick who attended. Each attendee gets the training on their 201 file / PDS (Learning &amp; Development).</p>
            <div class="row">
                <div class="field"><label for="c_from">From</label><input type="date" id="c_from" name="date_from" value="{{ $program->date_from?->toDateString() }}" required></div>
                <div class="field"><label for="c_to">To</label><input type="date" id="c_to" name="date_to" value="{{ $program->date_to?->toDateString() }}"></div>
                <div class="field"><label for="c_hours">Hours</label><input type="number" id="c_hours" name="hours" min="1" value="{{ $program->hours }}" required></div>
                <div class="field"><label for="c_cost">Actual cost (₱)</label><input type="number" step="0.01" id="c_cost" name="actual_cost" value="{{ $program->actual_cost }}"></div>
            </div>
            <button class="btn btn-primary" onclick="return confirm('Record this training as completed?')">Completed — record attendance</button>
            @endif
        </form>
        @foreach ($program->participants as $p)<form method="post" action="{{ route('admin.learning.participants.destroy', $p) }}" id="rm{{ $p->id }}">@csrf @method('delete')</form>@endforeach
        @endif
    </section>
    <div class="stack">
        @if (! $readOnly && $program->status !== 'done')
        <section class="card">
            <h2>Nominate</h2>
            @if ($suggested->isNotEmpty())<p class="small">Asked for this in the needs survey: <b>{{ $suggested->map->full_name->join(', ') }}</b></p>@endif
            <form method="post" action="{{ route('admin.learning.nominate', $program) }}">@csrf
                <div class="field"><label for="nominees">Employees</label><select id="nominees" name="employee_ids[]" multiple size="8" required>
                    @foreach ($employees as $e)<option value="{{ $e->id }}" @selected($suggested->contains('id', $e->id))>{{ $e->full_name }}{{ $e->office ? ' — '.$e->office : '' }}</option>@endforeach</select>
                    <div class="hint">Hold Ctrl/⌘ to pick several. They are notified.</div></div>
                <button class="btn btn-primary">Nominate</button>
            </form>
        </section>
        <section class="card">
            <h2>Details</h2>
            <form method="post" action="{{ route('admin.learning.update', $program) }}">@csrf @method('put')
                @include('admin.learning._fields', ['program' => $program])
                <div class="row">
                    <div class="field"><label for="p_status">Status</label><select id="p_status" name="status">@foreach (['planned', 'scheduled', 'cancelled'] as $st)<option value="{{ $st }}" @selected($program->status === $st)>{{ \App\Models\TrainingProgram::STATUSES[$st] }}</option>@endforeach</select></div>
                    <div class="field"><label for="p_cost">Actual cost (₱)</label><input type="number" step="0.01" id="p_cost" name="actual_cost" value="{{ $program->actual_cost }}"></div>
                </div>
                <button class="btn">Save</button>
            </form>
            <form method="post" action="{{ route('admin.learning.destroy', $program) }}" onsubmit="return confirm('Remove from the plan?')" style="margin-top:8px">@csrf @method('delete')<button class="btn btn-danger btn-sm">Remove from plan</button></form>
        </section>
        @endif
    </div>
</div>
@endsection
