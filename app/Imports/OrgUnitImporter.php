<?php

namespace App\Imports;

use App\Models\OrgStructure;
use App\Models\OrgUnit;
use App\Services\OrgService;
use Illuminate\Validation\Rule;

class OrgUnitImporter extends Importer
{
    public function key(): string
    {
        return 'org_units';
    }

    public function title(): string
    {
        return 'Organizational units';
    }

    public function description(): string
    {
        return 'Offices, departments, divisions and units of a structure, with their heads. Create the structure first (Organization → New structure). Rows are matched by structure and code.';
    }

    public function sectionRoute(): string
    {
        return 'admin.org.index';
    }

    public function columns(): array
    {
        return [
            ['name' => 'structure', 'label' => 'Structure name', 'required' => true, 'format' => 'Exact name of an existing structure', 'example' => 'Approved structure 2019', 'rules' => ['string']],
            ['name' => 'code', 'label' => 'Code', 'required' => true, 'format' => 'Short unique code within the structure', 'example' => 'FAD', 'rules' => ['string', 'max:30']],
            ['name' => 'name', 'label' => 'Name', 'required' => true, 'format' => 'Text', 'example' => 'Finance and Administration Department', 'rules' => ['string', 'max:200']],
            ['name' => 'level', 'label' => 'Level', 'required' => true, 'format' => 'office | department | division | unit', 'example' => 'department', 'rules' => [Rule::in(array_keys(OrgUnit::LEVELS))]],
            ['name' => 'parent_code', 'label' => 'Under (parent code)', 'format' => 'Code of a higher-level unit (blank = top level)', 'example' => 'OED', 'rules' => ['string', 'max:30']],
            ['name' => 'head_employee_no', 'label' => 'Head employee no.', 'format' => 'Employee no. (blank = vacant)', 'example' => '2015-001', 'rules' => ['string']],
            ['name' => 'head_title', 'label' => "Head's title", 'format' => 'Text', 'example' => 'Department Manager', 'rules' => ['string', 'max:100']],
            ['name' => 'sort', 'label' => 'Order', 'format' => 'Number (display order among siblings)', 'example' => '1', 'rules' => ['integer', 'min:0', 'max:999']],
        ];
    }

    public function moreExamples(): array
    {
        return [
            ['Approved structure 2019', 'OED', 'Office of the Executive Director', 'office', '', '', 'Executive Director', '0'],
            ['Approved structure 2019', 'FAD-ACC', 'Accounting Division', 'division', 'FAD', '', 'Division Chief', '1'],
            ['Approved structure 2019', 'FAD-ACC-PAY', 'Payroll Unit', 'unit', 'FAD-ACC', '', 'Unit Head', '1'],
            ['Approved structure 2019', 'OED-IA', 'Internal Audit Unit', 'unit', 'OED', '', 'Unit Head', '2'],
        ];
    }

    public function notes(): array
    {
        return ['Rows can be in any order — parents are linked after all rows are saved.', 'A unit can sit under any higher level (e.g. a unit directly under a department or the office).', 'Heads are added to the unit they head.'];
    }

    protected function check(array $row): array
    {
        $errors = [];
        if (! OrgStructure::where('name', $row['structure'])->exists()) {
            $errors[] = "Structure “{$row['structure']}” not found. Create it first in Organization.";
        }
        if ($row['head_employee_no'] && ! $this->employee($row['head_employee_no'])) {
            $errors[] = "Head employee no. “{$row['head_employee_no']}” not found.";
        }

        return $errors;
    }

    public function fileChecks(array $rows): array
    {
        $errors = [];
        foreach ($rows as $row) {
            if (! $row['parent_code']) {
                continue;
            }
            $inFile = collect($rows)->first(fn ($r) => $r['structure'] === $row['structure'] && $r['code'] === $row['parent_code']);
            $parentLevel = $inFile['level'] ?? OrgUnit::whereHas('structure', fn ($q) => $q->where('name', $row['structure']))->where('code', $row['parent_code'])->value('level');
            if (! $parentLevel) {
                $errors[$row['_line']][] = "Parent code “{$row['parent_code']}” is not in this file or the structure.";
            } elseif (OrgUnit::rank($parentLevel) >= OrgUnit::rank($row['level'])) {
                $errors[$row['_line']][] = "A {$row['level']} can't be under a {$parentLevel}.";
            }
        }

        return $errors;
    }

    public function save(array $row): string
    {
        $structure = OrgStructure::where('name', $row['structure'])->first();
        $unit = OrgUnit::firstOrNew(['org_structure_id' => $structure->id, 'code' => $row['code']]);
        $was = $unit->exists;
        $unit->fill(['name' => $row['name'], 'level' => $row['level'], 'head_employee_id' => $this->employee($row['head_employee_no'])?->id,
            'head_title' => $row['head_title'], 'sort' => $row['sort'] ?? 0])->save();

        return $was ? 'updated' : 'created';
    }

    public function finish(array $rows): void
    {
        foreach ($rows as $row) {
            $structure = OrgStructure::where('name', $row['structure'])->first();
            $unit = OrgUnit::where('org_structure_id', $structure->id)->where('code', $row['code'])->first();
            $unit->update(['parent_id' => $row['parent_code'] ? OrgUnit::where('org_structure_id', $structure->id)->where('code', $row['parent_code'])->value('id') : null]);
            if ($unit->head_employee_id) {
                app(OrgService::class)->assign($unit, [$unit->head_employee_id], $unit->head_title);
            }
        }
    }
}
