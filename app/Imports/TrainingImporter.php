<?php

namespace App\Imports;

use App\Models\Training;
use Illuminate\Validation\Rule;

class TrainingImporter extends RecordImporter
{
    public function key(): string
    {
        return 'trainings';
    }

    public function title(): string
    {
        return 'Learning & development (trainings)';
    }

    public function description(): string
    {
        return 'Trainings and seminars attended. Rows matching an existing entry (same employee, title and start date) are updated.';
    }

    public function columns(): array
    {
        return [
            $this->employeeColumn(),
            ['name' => 'title', 'label' => 'Title', 'required' => true, 'format' => 'Text', 'example' => 'Records Management Seminar', 'rules' => ['string', 'max:250']],
            ['name' => 'date_from', 'label' => 'From', 'required' => true, 'format' => 'Date', 'example' => '2026-03-02', 'rules' => ['date']],
            ['name' => 'date_to', 'label' => 'To', 'format' => 'Date', 'example' => '2026-03-03', 'rules' => ['date']],
            ['name' => 'hours', 'label' => 'Hours', 'format' => 'Number', 'example' => '16', 'rules' => ['integer', 'min:1', 'max:2000']],
            ['name' => 'type', 'label' => 'Type', 'format' => 'foundation | technical | supervisory | managerial', 'example' => 'technical', 'rules' => [Rule::in(array_keys(Training::TYPES))]],
            ['name' => 'sponsor', 'label' => 'Conducted / sponsored by', 'format' => 'Text', 'example' => 'Civil Service Commission', 'rules' => ['string', 'max:200']],
        ];
    }

    public function save(array $row): string
    {
        $r = Training::firstOrNew(['employee_id' => $this->employeeId($row), 'title' => $row['title'], 'date_from' => $row['date_from']]);
        $was = $r->exists;
        $r->fill(collect($row)->except(['employee_no', '_line'])->all())->save();

        return $was ? 'updated' : 'created';
    }
}
