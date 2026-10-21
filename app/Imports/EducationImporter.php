<?php

namespace App\Imports;

use App\Models\Education;
use Illuminate\Validation\Rule;

class EducationImporter extends RecordImporter
{
    public function key(): string
    {
        return 'education';
    }

    public function title(): string
    {
        return 'Educational background';
    }

    public function description(): string
    {
        return 'Schools and degrees for the 201 file and PDS. Rows matching an existing entry (same employee, level and school) are updated.';
    }

    public function columns(): array
    {
        return [
            $this->employeeColumn(),
            ['name' => 'level', 'label' => 'Level', 'required' => true, 'format' => 'elementary | secondary | vocational | college | graduate', 'example' => 'college', 'rules' => [Rule::in(array_keys(Education::LEVELS))]],
            ['name' => 'school', 'label' => 'School', 'required' => true, 'format' => 'Text', 'example' => 'University of the Philippines Diliman', 'rules' => ['string', 'max:200']],
            ['name' => 'degree', 'label' => 'Degree / course', 'format' => 'Text', 'example' => 'BS Economics', 'rules' => ['string', 'max:200']],
            ['name' => 'year_from', 'label' => 'From (year)', 'format' => 'Year YYYY', 'example' => '2008', 'rules' => ['digits:4']],
            ['name' => 'year_to', 'label' => 'To (year)', 'format' => 'Year YYYY', 'example' => '2012', 'rules' => ['digits:4']],
            ['name' => 'year_graduated', 'label' => 'Year graduated', 'format' => 'Year YYYY', 'example' => '2012', 'rules' => ['digits:4']],
            ['name' => 'honors', 'label' => 'Honors', 'format' => 'Text', 'example' => 'Cum Laude', 'rules' => ['string', 'max:200']],
        ];
    }

    public function save(array $row): string
    {
        $r = Education::firstOrNew(['employee_id' => $this->employeeId($row), 'level' => $row['level'], 'school' => $row['school']]);
        $was = $r->exists;
        $r->fill(collect($row)->except(['employee_no', '_line'])->all())->save();

        return $was ? 'updated' : 'created';
    }
}
