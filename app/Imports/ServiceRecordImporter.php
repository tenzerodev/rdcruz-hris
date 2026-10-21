<?php

namespace App\Imports;

use App\Models\ServiceRecord;

class ServiceRecordImporter extends RecordImporter
{
    public function key(): string
    {
        return 'service_records';
    }

    public function title(): string
    {
        return 'Service records';
    }

    public function description(): string
    {
        return 'Service record lines (appointments, promotions, salary changes). Rows matching an existing line (same employee, start date and designation) are updated.';
    }

    public function columns(): array
    {
        return [
            $this->employeeColumn(),
            ['name' => 'date_from', 'label' => 'From', 'required' => true, 'format' => 'Date', 'example' => '2019-06-03', 'rules' => ['date']],
            ['name' => 'date_to', 'label' => 'To', 'format' => 'Date (blank = present)', 'example' => '2022-12-31', 'rules' => ['date']],
            ['name' => 'designation', 'label' => 'Designation', 'required' => true, 'format' => 'Text', 'example' => 'Administrative Officer IV', 'rules' => ['string', 'max:150']],
            ['name' => 'status', 'label' => 'Status', 'required' => true, 'format' => 'Text (Permanent, Temporary, …)', 'example' => 'Permanent', 'rules' => ['string', 'max:50']],
            ['name' => 'annual_salary', 'label' => 'Annual salary', 'format' => 'Number', 'example' => '480000', 'rules' => ['numeric', 'min:0']],
            ['name' => 'office', 'label' => 'Station / place', 'format' => 'Text', 'example' => 'Administrative Division', 'rules' => ['string', 'max:150']],
            ['name' => 'branch', 'label' => 'Branch', 'format' => 'Text', 'example' => 'National', 'rules' => ['string', 'max:150']],
            ['name' => 'leave_without_pay', 'label' => 'Leave w/o pay', 'format' => 'Text', 'example' => '', 'rules' => ['string', 'max:100']],
            ['name' => 'separation', 'label' => 'Separation (date / cause)', 'format' => 'Text', 'example' => '', 'rules' => ['string', 'max:150']],
            ['name' => 'remarks', 'label' => 'Remarks', 'format' => 'Text', 'example' => 'Promotion', 'rules' => ['string', 'max:200']],
        ];
    }

    public function save(array $row): string
    {
        $r = ServiceRecord::firstOrNew(['employee_id' => $this->employeeId($row), 'date_from' => $row['date_from'], 'designation' => $row['designation']]);
        $was = $r->exists;
        $r->fill(collect($row)->except(['employee_no', '_line'])->all())->save();

        return $was ? 'updated' : 'created';
    }
}
