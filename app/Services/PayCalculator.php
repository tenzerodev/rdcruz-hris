<?php

namespace App\Services;

/**
 * Philippine government payroll math, matching rdcruz-ph-payroll (public/vendor/rdcruz-ph-payroll/payroll.js):
 * PhilHealth 5% shared (floor ₱10,000, ceiling ₱100,000), Pag-IBIG 2% up to ₱10,000, GSIS personal share,
 * and BIR withholding tax on compensation (TRAIN tables from 2023). Check rates against current circulars.
 */
class PayCalculator
{
    /** BIR tables: [over, base tax, rate on the excess]. */
    public const TAX_TABLES = [
        'monthly' => [[0, 0, 0], [20833, 0, 0.15], [33333, 1875, 0.20], [66667, 8541.80, 0.25], [166667, 33541.80, 0.30], [666667, 183541.80, 0.35]],
        'semi-monthly' => [[0, 0, 0], [10417, 0, 0.15], [16667, 937.50, 0.20], [33333, 4270.70, 0.25], [83333, 16770.70, 0.30], [333333, 91770.70, 0.35]],
        'annual' => [[0, 0, 0], [250000, 0, 0.15], [400000, 22500, 0.20], [800000, 102500, 0.25], [2000000, 402500, 0.30], [8000000, 2202500, 0.35]],
    ];

    public static function r2(float $n): float
    {
        return round($n + PHP_FLOAT_EPSILON, 2);
    }

    /** @return array{employee: float, employer: float} */
    public static function philhealth(float $monthlyBasic): array
    {
        $base = min(100000, max(10000, $monthlyBasic));
        $premium = self::r2($base * 0.05);
        $employee = self::r2($premium / 2);

        return ['employee' => $employee, 'employer' => self::r2($premium - $employee)];
    }

    /** @return array{employee: float, employer: float} */
    public static function pagibig(float $monthly): array
    {
        $base = min($monthly, 10000);
        $rate = $monthly <= 1500 ? 0.01 : 0.02;

        return ['employee' => self::r2($base * $rate), 'employer' => self::r2($base * 0.02)];
    }

    public static function withholdingTax(float $taxable, string $frequency = 'monthly'): float
    {
        foreach (array_reverse(self::TAX_TABLES[$frequency]) as [$over, $base, $rate]) {
            if ($taxable >= $over) {
                return self::r2(max(0, $base + ($taxable - $over) * $rate));
            }
        }

        return 0.0;
    }

    /**
     * Builds a payslip's lines and totals.
     *
     * @param  array{salary: float, attendance_deduction: float, earned: float, gsis_member: bool, contributions: bool, divisor: int,
     *     allowances: list<array{label: string, amount: float, taxable: bool}>, loans?: list<array{loan_id: int, lender: string, label: string, amount: float}>}  $in
     *     monthly amounts (loans: already per pay period); divisor 2 for semi-monthly
     * @param  list<array{label: string, amount: float, taxable?: bool}>  $extraEarnings  per pay period (entered by HR)
     * @param  list<array{label: string, amount: float}>  $extraDeductions  per pay period, after tax (loans etc.)
     * @return array{earnings: list<array>, deductions: list<array>, employer_share: array, gross: float, total_deductions: float, net: float, taxable: float, tax: float}
     */
    public function payslip(array $in, array $extraEarnings = [], array $extraDeductions = []): array
    {
        if (! empty($in['bonus'])) {
            return $this->bonus($in, $extraEarnings, $extraDeductions);
        }
        $n = max(1, (int) $in['divisor']);
        $per = fn (float $x) => self::r2($x / $n);
        $frequency = $n === 2 ? 'semi-monthly' : 'monthly';

        $earnings = [['label' => $in['basic_label'] ?? 'Basic salary', 'amount' => $per($in['salary']), 'taxable' => true, 'manual' => false]];
        if ($in['attendance_deduction'] > 0) {
            $earnings[] = ['label' => 'Less: absences, leave without pay, undertime', 'amount' => -$per($in['attendance_deduction']), 'taxable' => true, 'manual' => false];
        }
        $monthlyTaxableAllowances = 0.0;
        foreach ($in['allowances'] as $a) {
            $earnings[] = ['label' => $a['label'], 'amount' => $per((float) $a['amount']), 'taxable' => (bool) $a['taxable'], 'manual' => false];
            $monthlyTaxableAllowances += $a['taxable'] ? (float) $a['amount'] : 0;
        }
        foreach ($extraEarnings as $x) {
            $earnings[] = ['label' => $x['label'], 'amount' => self::r2((float) $x['amount']), 'taxable' => (bool) ($x['taxable'] ?? true), 'manual' => true];
        }

        $deductions = [];
        $employer = [];
        $contrib = 0.0;
        if ($in['contributions']) {
            $earned = (float) $in['earned'];
            $gsis = $in['gsis_member'] ? ['employee' => self::r2($earned * config('hris.payroll.gsis_employee')), 'employer' => self::r2($earned * config('hris.payroll.gsis_employer'))] : null;
            $ph = self::philhealth($earned);
            $pi = self::pagibig($earned);
            if ($gsis) {
                $deductions[] = ['label' => 'GSIS personal share', 'amount' => $per($gsis['employee']), 'kind' => 'mandatory', 'code' => 'gsis', 'manual' => false];
                $employer['GSIS'] = $per($gsis['employer']);
            }
            $deductions[] = ['label' => 'PhilHealth', 'amount' => $per($ph['employee']), 'kind' => 'mandatory', 'code' => 'philhealth', 'manual' => false];
            $deductions[] = ['label' => 'Pag-IBIG', 'amount' => $per($pi['employee']), 'kind' => 'mandatory', 'code' => 'pagibig', 'manual' => false];
            $employer['PhilHealth'] = $per($ph['employer']);
            $employer['Pag-IBIG'] = $per($pi['employer']);
            $contrib = $per(($gsis['employee'] ?? 0) + $ph['employee'] + $pi['employee']);
        }

        $taxable = 0.0;
        $tax = 0.0;
        if ($in['contributions']) {
            $taxableEarnings = array_sum(array_map(fn ($e) => $e['taxable'] ? $e['amount'] : 0, $earnings));
            $taxable = self::r2(max(0, $taxableEarnings - $contrib));
            $tax = self::withholdingTax($taxable, $frequency);
            if ($tax > 0) {
                $deductions[] = ['label' => 'Withholding tax', 'amount' => $tax, 'kind' => 'tax', 'code' => 'tax', 'manual' => false];
            }
        }
        foreach ($in['loans'] ?? [] as $l) {
            $deductions[] = ['label' => $l['label'], 'amount' => self::r2((float) $l['amount']), 'kind' => 'loan', 'loan_id' => $l['loan_id'], 'lender' => $l['lender'], 'manual' => false];
        }
        foreach ($extraDeductions as $x) {
            $deductions[] = ['label' => $x['label'], 'amount' => self::r2((float) $x['amount']), 'kind' => 'other', 'manual' => true];
        }

        return $this->totals($earnings, $deductions, $employer, $taxable, $tax);
    }

    /**
     * A bonus payslip (mid-year / year-end): benefit lines, no contributions; tax on any excess over the
     * yearly exemption is settled in the year-end adjustment.
     */
    private function bonus(array $in, array $extraEarnings, array $extraDeductions): array
    {
        $earnings = array_map(fn ($l) => $l + ['taxable' => false, 'benefit' => true, 'manual' => false], $in['lines']);
        foreach ($extraEarnings as $x) {
            $earnings[] = ['label' => $x['label'], 'amount' => self::r2((float) $x['amount']), 'taxable' => (bool) ($x['taxable'] ?? true), 'manual' => true];
        }
        $deductions = array_map(fn ($x) => ['label' => $x['label'], 'amount' => self::r2((float) $x['amount']), 'kind' => 'other', 'manual' => true], $extraDeductions);

        return $this->totals($earnings, $deductions, [], 0.0, 0.0);
    }

    private function totals(array $earnings, array $deductions, array $employer, float $taxable, float $tax): array
    {
        $gross = self::r2(array_sum(array_column($earnings, 'amount')));
        $total = self::r2(array_sum(array_column($deductions, 'amount')));

        return [
            'earnings' => $earnings,
            'deductions' => $deductions,
            'employer_share' => $employer,
            'gross' => $gross,
            'total_deductions' => $total,
            'net' => self::r2($gross - $total),
            'taxable' => $taxable,
            'tax' => $tax,
        ];
    }
}
