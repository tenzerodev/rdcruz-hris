<?php

namespace App\Imports;

use App\Models\OrgStructure;
use App\Models\OrgUnit;
use App\Services\OrgService;

class OrgAssignmentImporter extends Importer
{
    public function key(): string
    {
        return 'org_assignments';
    }

    public function title(): string
    {
        return 'Unit assignments (who is where)';
    }

    public function description(): string
    {
        return 'Place personnel in units of a structure. A person can be in one unit per structure; importing again moves them.';
    }

    public function sectionRoute(): string
    {
        return 'admin.org.index';
    }

    public function columns(): array
    {
        return [
            ['name' => 'structure', 'label' => 'Structure name', 'required' => true, 'format' => 'Exact name of an existing structure', 'example' => 'Approved structure 2019', 'rules' => ['string']],
            ['name' => 'unit_code', 'label' => 'Unit code', 'required' => true, 'format' => 'Code of a unit in that structure', 'example' => 'FAD-ACC', 'rules' => ['string']],
            ['name' => 'employee_no', 'label' => 'Employee no.', 'required' => true, 'format' => 'Text — must exist', 'example' => '2019-014', 'rules' => ['string']],
            ['name' => 'designation', 'label' => 'Designation in unit', 'format' => 'Text (optional, e.g. OIC)', 'example' => '', 'rules' => ['string', 'max:150']],
        ];
    }

    protected function check(array $row): array
    {
        $errors = [];
        $unit = OrgUnit::whereHas('structure', fn ($q) => $q->where('name', $row['structure']))->where('code', $row['unit_code'])->exists();
        if (! $unit) {
            $errors[] = "Unit “{$row['unit_code']}” not found in structure “{$row['structure']}”.";
        }
        if (! $this->employee($row['employee_no'])) {
            $errors[] = "Employee no. “{$row['employee_no']}” not found.";
        }

        return $errors;
    }

    public function save(array $row): string
    {
        $structure = OrgStructure::where('name', $row['structure'])->first();
        $unit = OrgUnit::where('org_structure_id', $structure->id)->where('code', $row['unit_code'])->first();
        app(OrgService::class)->assign($unit, [$this->employee($row['employee_no'])->id], $row['designation']);

        return 'created';
    }
}
