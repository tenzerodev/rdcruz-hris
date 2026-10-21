<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Position;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Plantilla of positions: items, salary grades and who holds them. */
class PositionController extends Controller
{
    public function index(Request $request)
    {
        $positions = Position::with('employee')->orderBy('office')->orderByDesc('salary_grade')->orderBy('item_no')->get();

        return view('admin.positions.index', [
            'positions' => $positions,
            'filled' => $positions->filter(fn ($p) => $p->employee)->count(),
            'edit' => $request->integer('edit') ? $positions->firstWhere('id', $request->integer('edit')) : null,
        ]);
    }

    public function store(Request $request)
    {
        $position = Position::create($this->validated($request));
        AuditLog::record('position.created', $position, $position->only('item_no', 'title'));

        return back()->with('status', 'Plantilla item added.');
    }

    public function update(Request $request, Position $position)
    {
        $position->update($this->validated($request, $position));
        AuditLog::record('position.updated', $position);

        return redirect()->route('admin.positions.index')->with('status', 'Plantilla item updated.');
    }

    public function destroy(Position $position)
    {
        abort_if($position->employee()->exists(), 422, 'This item is filled. Move the employee first.');
        AuditLog::record('position.deleted', $position, $position->only('item_no', 'title'));
        $position->delete();

        return back()->with('status', 'Plantilla item removed.');
    }

    private function validated(Request $request, ?Position $position = null): array
    {
        return $request->validate([
            'item_no' => ['required', 'string', 'max:60', Rule::unique('positions')->ignore($position)],
            'title' => ['required', 'string', 'max:150'],
            'salary_grade' => ['nullable', 'integer', 'min:1', 'max:33'],
            'office' => ['nullable', 'string', 'max:150'],
        ]) + ['is_active' => $request->boolean('is_active', true)];
    }
}
