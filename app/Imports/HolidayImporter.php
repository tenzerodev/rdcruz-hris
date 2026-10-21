<?php

namespace App\Imports;

use App\Models\Holiday;

class HolidayImporter extends Importer
{
    public function key(): string
    {
        return 'holidays';
    }

    public function title(): string
    {
        return 'Holidays & work suspensions';
    }

    public function description(): string
    {
        return 'Load the year’s proclaimed holidays, special non-working days and work suspensions. Rows are matched by date.';
    }

    public function sectionRoute(): string
    {
        return 'admin.holidays.index';
    }

    public function columns(): array
    {
        return [
            ['name' => 'date', 'label' => 'Date', 'required' => true, 'format' => 'Date', 'example' => '2027-01-01', 'rules' => ['date']],
            ['name' => 'name', 'label' => 'Name', 'required' => true, 'format' => 'Text', 'example' => "New Year's Day", 'rules' => ['string', 'max:100']],
            ['name' => 'type', 'label' => 'Type', 'format' => 'regular | special | suspension (blank = regular)', 'example' => 'regular', 'rules' => ['in:regular,special,suspension']],
        ];
    }

    public function moreExamples(): array
    {
        return [['2027-02-25', 'EDSA People Power Revolution Anniversary', 'special'], ['2027-07-24', 'Work suspension — typhoon', 'suspension']];
    }

    public function save(array $row): string
    {
        $h = Holiday::whereDate('date', $row['date'])->first();
        if ($h) {
            $h->update(['name' => $row['name'], 'type' => $row['type'] ?? 'regular']);

            return 'updated';
        }
        Holiday::create(['date' => $row['date'], 'name' => $row['name'], 'type' => $row['type'] ?? 'regular']);

        return 'created';
    }
}
