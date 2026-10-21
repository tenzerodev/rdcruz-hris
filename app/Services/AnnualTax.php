<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Payslip;
use Illuminate\Support\Collection;

/**
 * Annual compensation and tax per employee from released payslips — the figures for BIR Form 2316
 * and the year-end adjustment (tax due on the year's taxable pay vs. tax withheld).
 */
class AnnualTax
{
    /**
     * @return array{year: int, slips: int, gross: float, basic: float, benefits: float, benefits_exempt: float, benefits_taxable: float,
     *     other_non_taxable: float, contributions: float, taxable_regular: float, taxable: float, tax_due: float, withheld: float, difference: float}
     */
    public function forEmployee(Employee $employee, int $year): array
    {
        return $this->compute($year, Payslip::where('employee_id', $employee->id)
            ->whereHas('run', fn ($q) => $q->where('status', 'released')->where('period', 'like', $year.'-%'))->get());
    }

    /** @return Collection<int, array{employee: Employee}&array<string, mixed>> */
    public function forYear(int $year): Collection
    {
        return Payslip::with('employee')->whereHas('run', fn ($q) => $q->where('status', 'released')->where('period', 'like', $year.'-%'))->get()
            ->groupBy('employee_id')
            ->map(fn ($slips) => ['employee' => $slips->first()->employee] + $this->compute($year, $slips))
            ->sortBy(fn ($r) => $r['employee']->last_name.' '.$r['employee']->first_name)->values();
    }

    private function compute(int $year, Collection $slips): array
    {
        $earnings = $slips->flatMap(fn ($s) => $s->earnings);
        $benefits = (float) $earnings->filter(fn ($e) => ! empty($e['benefit']))->sum('amount');
        $otherNonTaxable = (float) $earnings->filter(fn ($e) => empty($e['benefit']) && empty($e['taxable']))->sum('amount');
        $contributions = (float) $slips->flatMap(fn ($s) => $s->deductions)->where('kind', 'mandatory')->sum('amount');
        $exempt = min($benefits, (float) config('hris.payroll.benefits_exemption'));
        $taxableRegular = (float) $slips->sum('taxable');
        $taxable = PayCalculator::r2($taxableRegular + ($benefits - $exempt));
        $taxDue = PayCalculator::withholdingTax($taxable, 'annual');
        $withheld = PayCalculator::r2((float) $slips->sum('tax'));

        return [
            'year' => $year,
            'slips' => $slips->count(),
            'gross' => PayCalculator::r2((float) $slips->sum('gross')),
            'basic' => PayCalculator::r2((float) $earnings->filter(fn ($e) => empty($e['benefit']) && ! empty($e['taxable']) && empty($e['manual']))->sum('amount')),
            'benefits' => PayCalculator::r2($benefits),
            'benefits_exempt' => PayCalculator::r2($exempt),
            'benefits_taxable' => PayCalculator::r2($benefits - $exempt),
            'other_non_taxable' => PayCalculator::r2($otherNonTaxable),
            'contributions' => PayCalculator::r2($contributions),
            'taxable_regular' => PayCalculator::r2($taxableRegular),
            'taxable' => $taxable,
            'tax_due' => $taxDue,
            'withheld' => $withheld,
            'difference' => PayCalculator::r2($taxDue - $withheld), // + to collect, − to refund
        ];
    }
}
