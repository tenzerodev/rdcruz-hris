<?php

namespace App\Imports;

use App\Models\Eligibility;

class EligibilityImporter extends RecordImporter
{
    public function key(): string
    {
        return 'eligibilities';
    }

    public function title(): string
    {
        return 'Civil service eligibility';
    }

    public function description(): string
    {
        return 'CSC eligibilities, board/bar ratings and licenses. Rows matching an existing entry (same employee and eligibility name) are updated.';
    }

    public function columns(): array
    {
        return [
            $this->employeeColumn(),
            ['name' => 'name', 'label' => 'Eligibility', 'required' => true, 'format' => 'Text', 'example' => 'Career Service Professional', 'rules' => ['string', 'max:200']],
            ['name' => 'rating', 'label' => 'Rating', 'format' => 'Text', 'example' => '85.50', 'rules' => ['string', 'max:20']],
            ['name' => 'exam_date', 'label' => 'Exam date', 'format' => 'Date', 'example' => '2013-08-04', 'rules' => ['date']],
            ['name' => 'exam_place', 'label' => 'Exam place', 'format' => 'Text', 'example' => 'Manila', 'rules' => ['string', 'max:200']],
            ['name' => 'license_no', 'label' => 'License no.', 'format' => 'Text', 'example' => '', 'rules' => ['string', 'max:60']],
            ['name' => 'valid_until', 'label' => 'Valid until', 'format' => 'Date', 'example' => '', 'rules' => ['date']],
        ];
    }

    public function save(array $row): string
    {
        $r = Eligibility::firstOrNew(['employee_id' => $this->employeeId($row), 'name' => $row['name']]);
        $was = $r->exists;
        $r->fill(collect($row)->except(['employee_no', '_line'])->all())->save();

        return $was ? 'updated' : 'created';
    }
}
