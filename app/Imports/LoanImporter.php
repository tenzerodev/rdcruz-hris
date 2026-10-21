<?php

namespace App\Imports;

use App\Models\Loan;

class LoanImporter extends Importer
{
    public function key(): string
    {
        return 'loans';
    }

    public function title(): string
    {
        return 'Loans & recurring deductions';
    }

    public function description(): string
    {
        return 'Bring over the loans and recurring deductions that payroll takes every month (GSIS, Pag-IBIG, cooperative, MP2, dues).';
    }

    public function sectionRoute(): string
    {
        return 'admin.loans.index';
    }

    public function columns(): array
    {
        $lenders = implode(' | ', array_keys(Loan::lenders()));

        return [
            ['name' => 'employee_no', 'label' => 'Employee no.', 'required' => true, 'format' => 'Text — must exist', 'example' => '2019-014', 'rules' => ['string']],
            ['name' => 'lender', 'label' => 'Remit to', 'required' => true, 'format' => $lenders, 'example' => 'gsis', 'rules' => ['in:'.implode(',', array_keys(Loan::lenders()))]],
            ['name' => 'name', 'label' => 'Name on the payslip', 'required' => true, 'format' => 'Text', 'example' => 'GSIS Consolidated Loan', 'rules' => ['string', 'max:120']],
            ['name' => 'reference_no', 'label' => 'Reference / loan no.', 'format' => 'Text', 'example' => 'CL-2025-00123', 'rules' => ['string', 'max:60']],
            ['name' => 'amortization', 'label' => 'Per month', 'required' => true, 'format' => 'Amount', 'example' => '3250.00', 'rules' => ['numeric', 'min:1']],
            ['name' => 'balance', 'label' => 'Remaining balance', 'format' => 'Amount still owed as of the first month (blank = no end, e.g. MP2 or dues)', 'example' => '58500.00', 'rules' => ['numeric', 'min:1']],
            ['name' => 'start_month', 'label' => 'First month', 'required' => true, 'format' => 'YYYY-MM', 'example' => '2026-11', 'rules' => ['date_format:Y-m']],
            ['name' => 'end_month', 'label' => 'Last month', 'format' => 'YYYY-MM (optional)', 'example' => '', 'rules' => ['date_format:Y-m']],
        ];
    }

    public function moreExamples(): array
    {
        return [['2019-014', 'pagibig', 'Pag-IBIG MP2 Savings', '', '500', '', '2026-11', '']];
    }

    public function notes(): array
    {
        return ['Enter what is still owed as the balance, not the original loan amount. Payments before the first month are not tracked here.', 'Importing the same file again adds the loans again.'];
    }

    protected function check(array $row): array
    {
        return $this->employee($row['employee_no']) ? [] : ["Employee no. “{$row['employee_no']}” not found."];
    }

    public function save(array $row): string
    {
        Loan::create([
            'employee_id' => $this->employee($row['employee_no'])->id, 'lender' => $row['lender'], 'name' => $row['name'],
            'kind' => $row['balance'] ? 'loan' : 'contribution', 'reference_no' => $row['reference_no'] ?: null,
            'amortization' => $row['amortization'], 'total_amount' => $row['balance'] ?: null,
            'start_period' => $row['start_month'], 'end_period' => $row['end_month'] ?: null, 'status' => 'active', 'created_by' => auth()->id(),
        ]);

        return 'created';
    }
}
