<?php

namespace App\Imports;

use App\Models\WfhArrangement;

class WfhImporter extends RecordImporter
{
    public function key(): string
    {
        return 'wfh';
    }

    public function title(): string
    {
        return 'Work-from-home arrangements';
    }

    public function description(): string
    {
        return 'Who may work from home, the period and which days (e.g. a rotation schedule from an office order).';
    }

    public function sectionRoute(): string
    {
        return 'admin.wfh.index';
    }

    public function columns(): array
    {
        return [
            $this->employeeColumn(),
            ['name' => 'date_from', 'label' => 'From', 'required' => true, 'format' => 'Date', 'example' => '2026-11-02', 'rules' => ['date']],
            ['name' => 'date_to', 'label' => 'To', 'required' => true, 'format' => 'Date', 'example' => '2026-11-27', 'rules' => ['date']],
            ['name' => 'days', 'label' => 'Days', 'format' => 'Mon, Tue, Wed, Thu, Fri, Sat separated by commas (blank = every work day)', 'example' => 'Mon, Wed', 'rules' => ['string', 'max:60']],
            ['name' => 'issued_computer_only', 'label' => 'Issued computer only', 'format' => 'yes or no (blank = yes)', 'example' => 'yes', 'rules' => ['in:yes,no,Yes,No,YES,NO,y,n,Y,N,1,0']],
            ['name' => 'reference_no', 'label' => 'Office order no.', 'format' => 'Text', 'example' => 'OO-2026-120', 'rules' => ['string', 'max:100']],
            ['name' => 'notes', 'label' => 'Notes', 'format' => 'Text', 'example' => 'Rotation A', 'rules' => ['string', 'max:250']],
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
        $bad = array_filter(self::days($row['days']), fn ($d) => $d === null);

        return $bad ? ['Days: use Mon, Tue, Wed, Thu, Fri or Sat.'] : [];
    }

    public function save(array $row): string
    {
        $days = array_values(array_unique(array_filter(self::days($row['days']))));
        sort($days);
        WfhArrangement::create([
            'employee_id' => $this->employeeId($row), 'date_from' => $row['date_from'], 'date_to' => $row['date_to'],
            'weekdays' => $days ?: null,
            'require_device' => ! in_array(strtolower((string) $row['issued_computer_only']), ['no', 'n', '0'], true),
            'reference_no' => $row['reference_no'], 'notes' => $row['notes'], 'created_by' => auth()->id(),
        ]);

        return 'created';
    }

    /** @return list<?int> ISO day numbers; null for a word that isn't a day. */
    private static function days(?string $value): array
    {
        $map = array_change_key_case(array_flip(WfhArrangement::DAYS));

        return collect(preg_split('/[\s,;\/]+/', (string) $value, -1, PREG_SPLIT_NO_EMPTY))
            ->map(fn ($d) => $map[strtolower(substr($d, 0, 3))] ?? null)->all();
    }
}
