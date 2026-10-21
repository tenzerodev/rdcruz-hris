<?php

namespace App\Imports;

/** Base for 201 file rows that belong to an employee. */
abstract class RecordImporter extends Importer
{
    public function sectionRoute(): string
    {
        return 'admin.employees.index';
    }

    protected function employeeColumn(): array
    {
        return ['name' => 'employee_no', 'label' => 'Employee no.', 'required' => true, 'format' => 'Text — must exist', 'example' => '2019-014', 'rules' => ['string']];
    }

    protected function check(array $row): array
    {
        return $this->employee($row['employee_no']) ? [] : ["Employee no. “{$row['employee_no']}” not found."];
    }

    protected function employeeId(array $row): int
    {
        return $this->employee($row['employee_no'])->id;
    }
}
