<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\OrgAssignment;
use App\Models\OrgStructure;
use App\Models\OrgUnit;
use Illuminate\Support\Facades\DB;

class OrgService
{
    /** Copy a structure (units, heads and assignments) — e.g. to start an interim structure from the approved one. */
    public function copy(OrgStructure $from, array $attrs): OrgStructure
    {
        return DB::transaction(function () use ($from, $attrs) {
            $to = OrgStructure::create($attrs + ['status' => 'draft']);
            $map = [];
            // Parents first, so children can point to the new parent ids.
            $units = $from->units()->get()->sortBy(fn ($u) => OrgUnit::rank($u->level));
            $pending = $units->values()->all();
            while ($pending) {
                $next = [];
                foreach ($pending as $u) {
                    if ($u->parent_id && ! isset($map[$u->parent_id])) {
                        $next[] = $u;

                        continue;
                    }
                    $copy = $u->replicate(['org_structure_id', 'parent_id']);
                    $copy->org_structure_id = $to->id;
                    $copy->parent_id = $u->parent_id ? $map[$u->parent_id] : null;
                    $copy->save();
                    $map[$u->id] = $copy->id;
                }
                if (count($next) === count($pending)) {
                    break; // orphaned rows (should not happen)
                }
                $pending = $next;
            }
            foreach ($from->assignments as $a) {
                if (isset($map[$a->org_unit_id])) {
                    OrgAssignment::create(['org_structure_id' => $to->id, 'org_unit_id' => $map[$a->org_unit_id], 'employee_id' => $a->employee_id, 'designation' => $a->designation]);
                }
            }
            AuditLog::record('org.copied', $to, ['from' => $from->id]);

            return $to;
        });
    }

    /** Make a structure active. Only one approved structure is active at a time (the old one is archived). */
    public function activate(OrgStructure $s): void
    {
        DB::transaction(function () use ($s) {
            if ($s->kind === 'approved') {
                OrgStructure::where('kind', 'approved')->where('status', 'active')->whereKeyNot($s->id)->update(['status' => 'archived']);
            }
            $s->update(['status' => 'active', 'effective_from' => $s->effective_from ?? today()]);
            AuditLog::record('org.activated', $s);
        });
        $this->syncOffices();
    }

    /** Assign people to a unit (moves them if they were elsewhere in the same structure). */
    public function assign(OrgUnit $unit, array $employeeIds, ?string $designation = null): void
    {
        foreach ($employeeIds as $id) {
            OrgAssignment::updateOrCreate(
                ['org_structure_id' => $unit->org_structure_id, 'employee_id' => $id],
                ['org_unit_id' => $unit->id, 'designation' => $designation ?: null],
            );
        }
        if ($unit->structure->isCurrent()) {
            $this->syncOffices($employeeIds);
        }
    }

    /** Keep each employee's "office" text in step with their unit in the structure in effect today. */
    public function syncOffices(?array $employeeIds = null): void
    {
        $current = OrgStructure::current();
        if (! $current) {
            return;
        }
        $current->assignments()->with('unit.parent.parent.parent')
            ->when($employeeIds, fn ($q) => $q->whereIn('employee_id', $employeeIds))->get()
            ->each(fn (OrgAssignment $a) => Employee::whereKey($a->employee_id)->update(['office' => $a->unit->name]));
    }

    /**
     * Set each person's immediate supervisor from the structure: the head of their unit, or — for the
     * head themselves — the head of the next unit up. Returns how many were changed.
     */
    public function syncSupervisors(OrgStructure $s): int
    {
        $changed = 0;
        foreach ($s->assignments()->with('unit')->get() as $a) {
            $boss = null;
            for ($u = $a->unit; $u; $u = $u->parent) {
                if ($u->head_employee_id && $u->head_employee_id !== $a->employee_id) {
                    $boss = $u->head_employee_id;
                    break;
                }
            }
            if ($boss && Employee::whereKey($a->employee_id)->where(fn ($q) => $q->whereNull('supervisor_id')->orWhere('supervisor_id', '!=', $boss))->update(['supervisor_id' => $boss])) {
                $changed++;
            }
        }
        AuditLog::record('org.supervisors_synced', $s, ['changed' => $changed]);

        return $changed;
    }

    /** Units as a nested tree: [unit, children[]] with members preloaded. */
    public function tree(OrgStructure $s): array
    {
        $units = $s->units()->with('head')->withCount('assignments')->orderBy('sort')->orderBy('name')->get();
        $byParent = $units->groupBy(fn ($u) => $u->parent_id ?? 0);
        $build = function ($parentId) use (&$build, $byParent) {
            return ($byParent[$parentId] ?? collect())->map(fn ($u) => ['unit' => $u, 'children' => $build($u->id)])->all();
        };

        return $build(0);
    }
}
