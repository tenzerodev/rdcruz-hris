<?php

namespace App\Imports;

use App\Models\Punch;
use Carbon\Carbon;

class AttendanceImporter extends Importer
{
    public function key(): string
    {
        return 'attendance';
    }

    public function title(): string
    {
        return 'Time records (attendance)';
    }

    public function description(): string
    {
        return 'Load time-in/out from the old biometric or logbook, one row per employee per day. Times already recorded for that day are kept; only empty ones are filled.';
    }

    public function sectionRoute(): string
    {
        return 'admin.punches.index';
    }

    public function columns(): array
    {
        return [
            ['name' => 'employee_no', 'label' => 'Employee no.', 'required' => true, 'format' => 'Text — must exist', 'example' => '2019-014', 'rules' => ['string']],
            ['name' => 'date', 'label' => 'Date', 'required' => true, 'format' => 'Date', 'example' => '2026-10-05', 'rules' => ['date', 'before_or_equal:today']],
            ['name' => 'am_in', 'label' => 'AM arrival', 'format' => 'Time (HH:MM or 8:05 AM)', 'example' => '07:58', 'rules' => []],
            ['name' => 'am_out', 'label' => 'AM departure', 'format' => 'Time', 'example' => '12:01', 'rules' => []],
            ['name' => 'pm_in', 'label' => 'PM arrival', 'format' => 'Time', 'example' => '12:55', 'rules' => []],
            ['name' => 'pm_out', 'label' => 'PM departure', 'format' => 'Time', 'example' => '17:10', 'rules' => []],
            ['name' => 'remarks', 'label' => 'Remarks', 'format' => 'Text', 'example' => 'From ZKTeco export', 'rules' => ['string', 'max:200']],
        ];
    }

    public function moreExamples(): array
    {
        return [['2019-014', '2026-10-06', '8:15 AM', '', '', '6:20 PM', '']];
    }

    public function notes(): array
    {
        return ['Fill in only the times you have; a day with just arrival and departure is fine.', 'Imported records are marked "import" in the attendance log, with your name in the audit log.'];
    }

    protected function check(array $row): array
    {
        if (! $this->employee($row['employee_no'])) {
            return ["Employee no. “{$row['employee_no']}” not found."];
        }
        if (! $row['am_in'] && ! $row['am_out'] && ! $row['pm_in'] && ! $row['pm_out']) {
            return ['Give at least one time.'];
        }

        return [];
    }

    public function save(array $row): string
    {
        $e = $this->employee($row['employee_no']);
        $added = 0;
        foreach (Punch::TYPES as $type) {
            if (! $row[$type]) {
                continue;
            }
            $exists = Punch::where('employee_id', $e->id)->whereDate('work_date', $row['date'])->where('type', $type)->exists();
            if (! $exists) {
                Punch::create(['employee_id' => $e->id, 'work_date' => $row['date'], 'type' => $type, 'punched_at' => Carbon::parse($row['date'].' '.$row[$type]),
                    'source' => 'import', 'created_by' => auth()->id(), 'remarks' => $row['remarks'] ?: 'Imported from CSV']);
                $added++;
            }
        }

        return $added ? 'created' : 'skipped';
    }
}
