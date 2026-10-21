@extends('layouts.app')
@section('title', 'Plantilla')
@section('content')
<div class="page-head"><div><h1>Plantilla of positions</h1><div class="muted">{{ $positions->count() }} items · {{ $filled }} filled · {{ $positions->count() - $filled }} vacant</div></div><x-import-button key="positions" /></div>
<div class="grid grid-2">
    <section class="card" style="grid-column: span 1">
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Item no.</th><th>Position</th><th>SG</th><th>Incumbent</th><th></th></tr></thead><tbody>
            @forelse ($positions as $p)
                <tr>
                    <td class="num small">{{ $p->item_no }}</td>
                    <td>{{ $p->title }}<div class="small muted">{{ $p->office }}</div></td>
                    <td class="num">{{ $p->salary_grade }}</td>
                    <td>@if ($p->employee)<a href="{{ route('admin.employees.show', $p->employee) }}">{{ $p->employee->full_name }}</a>@else<span class="badge badge-warn">vacant</span>@endif</td>
                    <td><div class="actions"><a class="btn btn-sm" href="{{ route('admin.positions.index', ['edit' => $p->id]) }}">Edit</a>
                        @unless ($p->employee)<form method="post" action="{{ route('admin.positions.destroy', $p) }}" onsubmit="return confirm('Remove this item?')">@csrf @method('delete')<button class="btn btn-sm btn-danger">Remove</button></form>@endunless</div></td>
                </tr>
            @empty <tr><td colspan="5" class="muted">No plantilla items yet.</td></tr> @endforelse
            </tbody></table></div>
    </section>
    <section class="card">
        <h2>{{ $edit ? 'Edit item '.$edit->item_no : 'Add plantilla item' }}</h2>
        <form method="post" action="{{ $edit ? route('admin.positions.update', $edit) : route('admin.positions.store') }}">
            @csrf @if ($edit) @method('put') @endif
            <div class="field"><label for="item_no">Item no.</label><input type="text" id="item_no" name="item_no" value="{{ old('item_no', $edit?->item_no) }}" required></div>
            <div class="field"><label for="title">Position title</label><input type="text" id="title" name="title" value="{{ old('title', $edit?->title) }}" required></div>
            <div class="row">
                <div class="field"><label for="salary_grade">Salary grade</label><input type="number" id="salary_grade" name="salary_grade" min="1" max="33" value="{{ old('salary_grade', $edit?->salary_grade) }}"></div>
                <div class="field"><label for="office">Office</label><input type="text" id="office" name="office" value="{{ old('office', $edit?->office) }}"></div>
            </div>
            <input type="hidden" name="is_active" value="0"><label class="check"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $edit?->is_active ?? true))> Active (funded)</label>
            <div class="actions" style="justify-content:flex-start;margin-top:12px">
                <button class="btn btn-primary">Save</button>
                @if ($edit)<a class="btn" href="{{ route('admin.positions.index') }}">Cancel</a>@endif
            </div>
        </form>
        <p class="hint">Assign an item to an employee from their Edit page.</p>
    </section>
</div>
@endsection
