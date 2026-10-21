<?php

namespace App\Imports;

use App\Models\LeaveApplication;
use App\Services\LeaveService;
use Illuminate\Validation\Rule;

class LeaveHistoryImporter extends RecordImporter
{
    public function key(): string
    {
        return 'leave_history';
    }

    public function title(): string
    {
        return 'Past approved leave';
    }

    public function description(): string
    {
        return 'Leave already approved on paper, so those days show as leave on the DTR (and count for the weekend-overtime rule). This does not change leave balances — import beginning balances for that.';
    }

    public function sectionRoute(): string
    {
        return 'admin.leave.index';
    }

    public function columns(): array
    {
        return [
            $this->employeeColumn(),
            ['name' => 'type', 'label' => 'Leave type', 'required' => true, 'format' => 'Code: '.implode(' | ', array_keys(config('hris.leave_types'))), 'example' => 'VL', 'rules' => [Rule::in(array_merge(array_keys(config('hris.leave_types')), array_map('strtolower', array_keys(config('hris.leave_types')))))]],
            ['name' => 'date_from', 'label' => 'From', 'required' => true, 'format' => 'Date', 'example' => '2026-09-14', 'rules' => ['date']],
            ['name' => 'date_to', 'label' => 'To', 'required' => true, 'format' => 'Date', 'example' => '2026-09-15', 'rules' => ['date']],
            ['name' => 'half_day', 'label' => 'Half day', 'format' => 'am | pm (single day only)', 'example' => '', 'rules' => ['in:am,pm']],
            ['name' => 'days_without_pay', 'label' => 'Days without pay', 'format' => 'Number (blank = 0)', 'example' => '0', 'rules' => ['numeric', 'min:0']],
            ['name' => 'remarks', 'label' => 'Remarks', 'format' => 'Text', 'example' => 'Approved on paper, LV-2026-114', 'rules' => ['string', 'max:250']],
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
        $overlap = LeaveApplication::where('employee_id', $this->employeeId($row))->whereIn('status', ['pending', 'recommended', 'approved'])
            ->whereDate('date_from', '<=', $row['date_to'])->whereDate('date_to', '>=', $row['date_from'])->exists();

        return $overlap ? ['There is already leave on these dates.'] : [];
    }

    public function save(array $row): string
    {
        $half = $row['date_from'] === $row['date_to'] ? $row['half_day'] : null;
        $days = app(LeaveService::class)->workingDays($row['date_from'], $row['date_to'], $half);
        $without = min($days, (float) ($row['days_without_pay'] ?? 0));
        LeaveApplication::create([
            'employee_id' => $this->employeeId($row), 'type' => strtoupper($row['type']), 'date_from' => $row['date_from'], 'date_to' => $row['date_to'],
            'half_day' => $half, 'days' => $days, 'status' => 'approved', 'filed_by' => auth()->id(), 'approved_by' => auth()->id(), 'approved_at' => now(),
            'days_with_pay' => $days - $without, 'days_without_pay' => $without, 'remarks' => $row['remarks'] ?: 'Imported from CSV',
        ]);

        return 'created';
    }
}
