<?php

namespace App\Imports;

use App\Models\Employee;
use App\Models\TravelOrder;
use Illuminate\Validation\Rule;

class TravelImporter extends Importer
{
    public function key(): string
    {
        return 'travel';
    }

    public function title(): string
    {
        return 'Travel orders / OB';
    }

    public function description(): string
    {
        return 'Approved travel orders, official business, trainings or work-from-home. Several people can share one order.';
    }

    public function sectionRoute(): string
    {
        return 'admin.travel.index';
    }

    public function columns(): array
    {
        return [
            ['name' => 'employee_nos', 'label' => 'Employee no(s).', 'required' => true, 'format' => 'One or more, separated by ; (semicolon)', 'example' => '2019-014; COS-2026-07', 'rules' => ['string']],
            ['name' => 'type', 'label' => 'Type', 'required' => true, 'format' => 'ob | travel | training | wfh', 'example' => 'travel', 'rules' => [Rule::in(array_keys(TravelOrder::TYPES))]],
            ['name' => 'date_from', 'label' => 'From', 'required' => true, 'format' => 'Date', 'example' => '2026-11-03', 'rules' => ['date']],
            ['name' => 'date_to', 'label' => 'To', 'required' => true, 'format' => 'Date', 'example' => '2026-11-05', 'rules' => ['date']],
            ['name' => 'time_from', 'label' => 'Time from', 'format' => 'Time (partial-day OB only)', 'example' => '', 'rules' => []],
            ['name' => 'time_to', 'label' => 'Time to', 'format' => 'Time', 'example' => '', 'rules' => []],
            ['name' => 'destination', 'label' => 'Destination', 'required' => true, 'format' => 'Text', 'example' => 'Cebu City', 'rules' => ['string', 'max:200']],
            ['name' => 'purpose', 'label' => 'Purpose', 'required' => true, 'format' => 'Text', 'example' => 'Regional trade fair', 'rules' => ['string', 'max:250']],
            ['name' => 'reference_no', 'label' => 'Order no.', 'format' => 'Text', 'example' => 'TO-2026-077', 'rules' => ['string', 'max:60']],
        ];
    }

    private function nos(?string $v): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[;|]/', (string) $v))));
    }

    protected function check(array $row): array
    {
        $errors = [];
        foreach ($this->nos($row['employee_nos']) as $no) {
            if (! $this->employee($no)) {
                $errors[] = "Employee no. “{$no}” not found.";
            }
        }
        if ($row['date_to'] < $row['date_from']) {
            $errors[] = '“To” is before “From”.';
        }
        if (($row['time_from'] xor $row['time_to']) || ($row['time_from'] && $row['time_to'] <= $row['time_from'])) {
            $errors[] = 'Give both times, with “Time to” after “Time from”.';
        }

        return $errors;
    }

    public function save(array $row): string
    {
        $order = TravelOrder::create(collect($row)->only(['type', 'date_from', 'date_to', 'time_from', 'time_to', 'destination', 'purpose', 'reference_no'])->all()
            + ['status' => 'approved', 'requested_by' => auth()->id(), 'approved_by' => auth()->id(), 'approved_at' => now()]);
        $order->employees()->sync(Employee::whereIn('employee_no', $this->nos($row['employee_nos']))->pluck('id'));

        return 'created';
    }
}
