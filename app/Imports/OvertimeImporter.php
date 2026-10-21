<?php

namespace App\Imports;

use App\Models\OvertimeAuthorization;

class OvertimeImporter extends RecordImporter
{
    public function key(): string
    {
        return 'overtime';
    }

    public function title(): string
    {
        return 'Overtime authorities';
    }

    public function description(): string
    {
        return 'Approved overtime (office orders) per employee and dates. Imported as approved.';
    }

    public function sectionRoute(): string
    {
        return 'admin.overtime.index';
    }

    public function columns(): array
    {
        return [
            $this->employeeColumn(),
            ['name' => 'date_from', 'label' => 'From', 'required' => true, 'format' => 'Date', 'example' => '2026-10-10', 'rules' => ['date']],
            ['name' => 'date_to', 'label' => 'To', 'required' => true, 'format' => 'Date', 'example' => '2026-10-11', 'rules' => ['date']],
            ['name' => 'max_hours_per_day', 'label' => 'Max hours per day', 'format' => 'Number (blank = no limit; at least 2 to be claimable)', 'example' => '4', 'rules' => ['numeric', 'min:0.5', 'max:16']],
            ['name' => 'purpose', 'label' => 'Purpose', 'required' => true, 'format' => 'Text', 'example' => 'Trade fair booth setup', 'rules' => ['string', 'max:250']],
            ['name' => 'reference_no', 'label' => 'Office order no.', 'format' => 'Text', 'example' => 'OO-2026-101', 'rules' => ['string', 'max:100']],
        ];
    }

    protected function check(array $row): array
    {
        if ($errors = parent::check($row)) {
            return $errors;
        }
        if ($row['date_to'] < $row['date_from']) {
            return ['“To” is before “From”.'];
        }

        return $this->employee($row['employee_no'])->overtimeEligible() ? [] : [$this->employee($row['employee_no'])->type_label.' personnel cannot claim overtime.'];
    }

    public function save(array $row): string
    {
        OvertimeAuthorization::create([
            'employee_id' => $this->employeeId($row), 'date_from' => $row['date_from'], 'date_to' => $row['date_to'],
            'max_minutes_per_day' => $row['max_hours_per_day'] ? (int) round($row['max_hours_per_day'] * 60) : null,
            'purpose' => $row['purpose'], 'reference_no' => $row['reference_no'], 'status' => 'approved',
            'requested_by' => auth()->id(), 'approved_by' => auth()->id(), 'decided_at' => now(),
        ]);

        return 'created';
    }
}
