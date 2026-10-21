<?php

namespace App\Imports;

use App\Models\LeaveLedger;

class LeaveBalanceImporter extends Importer
{
    public function key(): string
    {
        return 'leave_balances';
    }

    public function title(): string
    {
        return 'Beginning leave balances';
    }

    public function description(): string
    {
        return 'Bring over balances from the old leave cards (VL, SL, SPL, COC). Each row adds an adjustment line to the employee’s leave card.';
    }

    public function sectionRoute(): string
    {
        return 'admin.leave.posting';
    }

    public function columns(): array
    {
        return [
            ['name' => 'employee_no', 'label' => 'Employee no.', 'required' => true, 'format' => 'Text — must exist', 'example' => '2019-014', 'rules' => ['string']],
            ['name' => 'balance', 'label' => 'Balance', 'required' => true, 'format' => 'VL | SL | SPL | COC (COC in hours)', 'example' => 'VL', 'rules' => ['in:VL,SL,SPL,COC,vl,sl,spl,coc']],
            ['name' => 'as_of_date', 'label' => 'As of date', 'required' => true, 'format' => 'Date', 'example' => '2025-12-31', 'rules' => ['date']],
            ['name' => 'amount', 'label' => 'Amount', 'required' => true, 'format' => 'Number of days (hours for COC), up to 3 decimals', 'example' => '42.125', 'rules' => ['numeric', 'min:0', 'max:1000']],
            ['name' => 'particulars', 'label' => 'Particulars', 'format' => 'Text (blank = “Balance forwarded”)', 'example' => 'Balance forwarded from old leave card', 'rules' => ['string', 'max:200']],
        ];
    }

    public function moreExamples(): array
    {
        return [['2019-014', 'SL', '2025-12-31', '61.5', 'Balance forwarded from old leave card']];
    }

    public function notes(): array
    {
        return ['Import each balance once. Importing the same file again adds the amounts again — delete wrong lines on the leave card if needed.', 'Use the balance as of the day before you start month-end posting in this system.'];
    }

    protected function check(array $row): array
    {
        $e = $this->employee($row['employee_no']);

        return match (true) {
            ! $e => ["Employee no. “{$row['employee_no']}” not found."],
            ! $e->earnsLeave() && strtoupper($row['balance']) !== 'COC' => ["{$e->type_label} personnel have no leave credits."],
            default => [],
        };
    }

    public function save(array $row): string
    {
        LeaveLedger::create([
            'employee_id' => $this->employee($row['employee_no'])->id, 'balance' => strtoupper($row['balance']), 'entry_date' => $row['as_of_date'],
            'particulars' => $row['particulars'] ?: 'Balance forwarded', 'earned' => $row['amount'], 'source' => 'adjustment', 'created_by' => auth()->id(),
        ]);

        return 'created';
    }
}
