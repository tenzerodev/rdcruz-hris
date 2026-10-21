<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\OrgAssignment;
use App\Models\OrgStructure;
use App\Models\OrgUnit;
use App\Services\OrgService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrgController extends Controller
{
    public function __construct(private OrgService $org) {}

    public function index()
    {
        return view('admin.org.index', [
            'structures' => OrgStructure::withCount(['units', 'assignments'])->orderByRaw("case status when 'active' then 0 when 'draft' then 1 else 2 end")->latest('effective_from')->get(),
            'current' => OrgStructure::current(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'kind' => ['required', 'in:approved,interim'],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'reference' => ['nullable', 'string', 'max:200'],
            'copy_from' => ['nullable', 'exists:org_structures,id'],
        ]);
        $attrs = collect($data)->except('copy_from')->all();
        $s = ! empty($data['copy_from'])
            ? $this->org->copy(OrgStructure::findOrFail($data['copy_from']), $attrs)
            : OrgStructure::create($attrs + ['status' => 'draft']);
        AuditLog::record('org.created', $s, $attrs);

        return redirect()->route('admin.org.show', $s)->with('status', 'Structure created as a draft. Add or adjust units, then activate it.');
    }

    public function show(Request $request, OrgStructure $structure)
    {
        $assigned = $structure->assignments()->pluck('employee_id');

        return view('admin.org.show', [
            's' => $structure,
            'tree' => $this->org->tree($structure),
            'units' => $structure->units()->orderBy('name')->get(),
            'edit' => $request->integer('unit') ? $structure->units()->find($request->integer('unit')) : null,
            'members' => $request->integer('unit')
                ? OrgAssignment::with('employee')->where('org_unit_id', $request->integer('unit'))->get()->sortBy('employee.last_name')
                : collect(),
            'employees' => Employee::where('is_active', true)->orderBy('last_name')->get(),
            'unassigned' => Employee::where('is_active', true)->whereNotIn('id', $assigned)->orderBy('last_name')->get(),
            'isCurrent' => $structure->isCurrent(),
        ]);
    }

    public function update(Request $request, OrgStructure $structure)
    {
        if ($request->filled('action')) {
            match ($request->input('action')) {
                'activate' => $this->org->activate($structure),
                'archive' => $structure->update(['status' => 'archived']),
            };
            AuditLog::record('org.'.$request->input('action'), $structure);

            return back()->with('status', $request->input('action') === 'activate' ? 'Structure is now active.' : 'Structure archived.');
        }
        $structure->update($request->validate([
            'name' => ['required', 'string', 'max:150'],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'reference' => ['nullable', 'string', 'max:200'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]));
        AuditLog::record('org.updated', $structure);

        return back()->with('status', 'Structure details saved.');
    }

    public function destroy(OrgStructure $structure)
    {
        abort_if($structure->status === 'active', 422, 'Archive the structure before deleting it.');
        AuditLog::record('org.deleted', $structure, ['name' => $structure->name]);
        $structure->delete();

        return redirect()->route('admin.org.index')->with('status', 'Structure deleted.');
    }

    public function storeUnit(Request $request, OrgStructure $structure)
    {
        $unit = $structure->units()->create($this->validatedUnit($request, $structure));
        $this->headAssignment($unit);
        AuditLog::record('org.unit_added', $unit, $unit->only('name', 'level'));

        return redirect()->route('admin.org.show', [$structure, 'unit' => $unit->id])->with('status', "{$unit->level_label} “{$unit->name}” added.");
    }

    public function updateUnit(Request $request, OrgStructure $structure, OrgUnit $unit)
    {
        abort_unless($unit->org_structure_id === $structure->id, 404);
        $unit->update($this->validatedUnit($request, $structure, $unit));
        $this->headAssignment($unit);
        AuditLog::record('org.unit_updated', $unit);

        return back()->with('status', 'Unit saved.');
    }

    public function destroyUnit(OrgStructure $structure, OrgUnit $unit)
    {
        abort_unless($unit->org_structure_id === $structure->id, 404);
        abort_if($unit->children()->exists(), 422, 'Move or remove the units under it first.');
        AuditLog::record('org.unit_deleted', $unit, $unit->only('name', 'level'));
        $unit->delete();

        return redirect()->route('admin.org.show', $structure)->with('status', 'Unit removed. Its people are now unassigned in this structure.');
    }

    public function assign(Request $request, OrgStructure $structure, OrgUnit $unit)
    {
        abort_unless($unit->org_structure_id === $structure->id, 404);
        $data = $request->validate(['employee_ids' => ['required', 'array', 'min:1'], 'employee_ids.*' => ['exists:employees,id'], 'designation' => ['nullable', 'string', 'max:150']]);
        $this->org->assign($unit, $data['employee_ids'], $data['designation'] ?? null);
        AuditLog::record('org.assigned', $unit, ['employees' => $data['employee_ids']]);

        return back()->with('status', count($data['employee_ids']).' person(s) assigned to '.$unit->name.'.');
    }

    public function unassign(OrgStructure $structure, OrgAssignment $assignment)
    {
        abort_unless($assignment->org_structure_id === $structure->id, 404);
        $assignment->delete();

        return back()->with('status', 'Removed from the unit.');
    }

    public function syncSupervisors(OrgStructure $structure)
    {
        $n = $this->org->syncSupervisors($structure);

        return back()->with('status', "Supervisors updated for $n person(s) from the unit heads.");
    }

    private function validatedUnit(Request $request, OrgStructure $structure, ?OrgUnit $unit = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'code' => ['nullable', 'string', 'max:30', Rule::unique('org_units')->where('org_structure_id', $structure->id)->ignore($unit)],
            'level' => ['required', Rule::in(array_keys(OrgUnit::LEVELS))],
            'parent_id' => ['nullable', Rule::exists('org_units', 'id')->where('org_structure_id', $structure->id)],
            'head_employee_id' => ['nullable', 'exists:employees,id'],
            'head_title' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);
        if ($data['parent_id'] ?? null) {
            $parent = OrgUnit::find($data['parent_id']);
            if (OrgUnit::rank($parent->level) >= OrgUnit::rank($data['level'])) {
                abort(back()->withInput()->withErrors(['parent_id' => "A {$data['level']} can't be under a {$parent->level}. Choose a higher-level parent."]));
            }
            if ($unit && in_array($unit->id, array_map(fn ($u) => $u->id, $parent->path()), true)) {
                abort(back()->withInput()->withErrors(['parent_id' => 'A unit can’t be placed under itself.']));
            }
        }

        return $data + ['sort' => $data['sort'] ?? 0];
    }

    /** The head belongs to the unit they head. */
    private function headAssignment(OrgUnit $unit): void
    {
        if ($unit->head_employee_id) {
            $this->org->assign($unit, [$unit->head_employee_id], $unit->head_title);
        }
    }
}
