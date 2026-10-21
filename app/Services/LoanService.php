<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Loan;
use App\Models\LoanPayment;
use App\Models\PayrollRun;
use Illuminate\Support\Facades\DB;

/**
 * Loans and recurring deductions on payslips: which lines go on a draft payslip, and the payment
 * history (written when a payroll is released, undone when it is withdrawn).
 */
class LoanService
{
    /**
     * Loan lines for one employee's payslip in a regular payroll (not bonuses).
     *
     * @return list<array{loan_id: int, lender: string, label: string, amount: float}>
     */
    public function linesFor(PayrollRun $run, Employee $employee): array
    {
        if ($run->isBonus()) {
            return [];
        }
        $share = match (true) {
            $run->cut === 'full' => 1.0,
            config('hris.payroll.loan_deduction') === 'second' => $run->cut === 'second' ? 1.0 : 0.0,
            default => 0.5,
        };
        if ($share === 0.0) {
            return [];
        }

        $lines = [];
        $loans = Loan::where('employee_id', $employee->id)->where('status', 'active')->withSum('payments', 'amount')->orderBy('id')->get();
        foreach ($loans as $loan) {
            if (! $loan->appliesTo($run->period)) {
                continue;
            }
            $amount = round($loan->amortization * $share, 2);
            if (($balance = $loan->balance()) !== null) {
                $amount = min($amount, $balance);
            }
            if ($amount > 0) {
                $lines[] = ['loan_id' => $loan->id, 'lender' => $loan->lender, 'label' => $loan->line_label, 'amount' => $amount];
            }
        }

        return $lines;
    }

    /** Writes the run's loan payments and closes loans that are now fully paid. */
    public function recordPayments(PayrollRun $run): void
    {
        DB::transaction(function () use ($run) {
            foreach ($run->payslips as $slip) {
                foreach ($slip->deductions ?? [] as $line) {
                    if (($line['kind'] ?? null) !== 'loan' || empty($line['loan_id']) || ! Loan::whereKey($line['loan_id'])->exists()) {
                        continue;
                    }
                    LoanPayment::updateOrCreate(['loan_id' => $line['loan_id'], 'payslip_id' => $slip->id], ['period' => $run->period, 'amount' => $line['amount']]);
                }
            }
            $ids = LoanPayment::whereIn('payslip_id', $run->payslips->pluck('id'))->pluck('loan_id')->unique();
            Loan::whereIn('id', $ids)->where('status', 'active')->whereNotNull('total_amount')->withSum('payments', 'amount')->get()
                ->filter(fn (Loan $l) => $l->balance() <= 0)
                ->each(fn (Loan $l) => $l->update(['status' => 'paid']));
        });
    }

    /** Undoes recordPayments() for a run taken back to draft. */
    public function reversePayments(PayrollRun $run): void
    {
        DB::transaction(function () use ($run) {
            $payments = LoanPayment::whereIn('payslip_id', $run->payslips()->pluck('id'));
            $ids = (clone $payments)->pluck('loan_id')->unique();
            $payments->delete();
            Loan::whereIn('id', $ids)->where('status', 'paid')->withSum('payments', 'amount')->get()
                ->filter(fn (Loan $l) => $l->balance() > 0)
                ->each(fn (Loan $l) => $l->update(['status' => 'active']));
        });
    }
}
