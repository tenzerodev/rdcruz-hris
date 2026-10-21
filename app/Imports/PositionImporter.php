<?php

namespace App\Imports;

use App\Models\Position;

class PositionImporter extends Importer
{
    public function key(): string
    {
        return 'positions';
    }

    public function title(): string
    {
        return 'Plantilla items';
    }

    public function description(): string
    {
        return 'Add or update plantilla items. Rows are matched by item number.';
    }

    public function sectionRoute(): string
    {
        return 'admin.positions.index';
    }

    public function columns(): array
    {
        return [
            ['name' => 'item_no', 'label' => 'Item no.', 'required' => true, 'format' => 'Text, unique', 'example' => 'ITEM-AO5-1', 'rules' => ['string', 'max:60']],
            ['name' => 'title', 'label' => 'Position title', 'required' => true, 'format' => 'Text', 'example' => 'Administrative Officer V', 'rules' => ['string', 'max:150']],
            ['name' => 'salary_grade', 'label' => 'Salary grade', 'format' => 'Number 1–33', 'example' => '18', 'rules' => ['integer', 'between:1,33']],
            ['name' => 'office', 'label' => 'Office', 'format' => 'Text', 'example' => 'Administrative Division', 'rules' => ['string', 'max:150']],
        ];
    }

    public function moreExamples(): array
    {
        return [['ITEM-AO2-1', 'Administrative Officer II', '11', 'Administrative Division']];
    }

    public function save(array $row): string
    {
        $p = Position::firstOrNew(['item_no' => $row['item_no']]);
        $was = $p->exists;
        $p->fill(['title' => $row['title'], 'salary_grade' => $row['salary_grade'], 'office' => $row['office'], 'is_active' => true])->save();

        return $was ? 'updated' : 'created';
    }
}
