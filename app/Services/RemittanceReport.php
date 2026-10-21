<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\Payslip;
use Illuminate\Support\Collection;

/**
 * Monthly remittance lists from released payslips: GSIS, PhilHealth, Pag-IBIG, BIR withholding tax,
 * and loans or deductions owed to cooperatives, banks and others.
 */
class RemittanceReport
{
    /**
     * @return array<string, array{title: string, id_label: ?string, columns: list<string>, rows: list<array{employee_no: string, name: string, id: ?string, values: list<float>}>, totals: list<float>}>
     */
    public function build(string $period): array
    {
        $slips = Payslip::with('employee', 'run')
            ->whereHas('run', fn ($q) => $q->where('status', 'released')->where('period', $period))
            ->get()->groupBy('employee_id');

        $lenders = Loan::lenders();
        $sections = [
            'gsis' => ['title' => 'GSIS', 'id_label' => 'GSIS BP no.', 'id' => 'gsis_no', 'fixed' => ['Basic salary', 'Personal share', 'Government share']],
            'philhealth' => ['title' => 'PhilHealth', 'id_label' => 'PhilHealth no.', 'id' => 'philhealth_no', 'fixed' => ['Employee share', 'Employer share']],
            'pagibig' => ['title' => 'Pag-IBIG Fund', 'id_label' => 'Pag-IBIG MID', 'id' => 'pagibig_no', 'fixed' => ['Employee share', 'Employer share']],
            'bir' => ['title' => 'BIR — withholding tax on compensation', 'id_label' => 'TIN', 'id' => 'tin', 'fixed' => ['Gross pay', 'Taxable pay', 'Tax withheld']],
        ];
        foreach (['coop', 'bank', 'other'] as $l) {
            $sections[$l] = ['title' => $lenders[$l] ?? ucfirst($l), 'id_label' => null, 'id' => null, 'fixed' => []];
        }

        // section => employee_id => column => amount
        $cells = [];
        $extra = []; // section => [loan labels]
        foreach ($slips as $employeeId => $group) {
            foreach ($group as $slip) {
                $add = function (string $section, string $column, float $amount) use (&$cells, &$extra, $employeeId, $sections) {
                    if ($amount == 0.0) {
                        return;
                    }
                    if (! in_array($column, $sections[$section]['fixed'], true)) {
                        $extra[$section][$column] = true;
                    }
                    $cells[$section][$employeeId][$column] = ($cells[$section][$employeeId][$column] ?? 0) + $amount;
                };
                $er = $slip->employer_share ?? [];
                foreach ($slip->deductions ?? [] as $d) {
                    $code = $d['code'] ?? match ($d['label']) {
                        'GSIS personal share' => 'gsis', 'PhilHealth' => 'philhealth', 'Pag-IBIG' => 'pagibig', 'Withholding tax' => 'tax', default => null,
                    };
                    $amount = (float) $d['amount'];
                    match (true) {
                        $code === 'gsis' => $add('gsis', 'Personal share', $amount),
                        $code === 'philhealth' => $add('philhealth', 'Employee share', $amount),
                        $code === 'pagibig' => $add('pagibig', 'Employee share', $amount),
                        $code === 'tax' => $add('bir', 'Tax withheld', $amount),
                        ($d['kind'] ?? null) === 'loan' => $add($this->sectionFor($d['lender'] ?? 'other'), $this->column($d['label']), $amount),
                        default => $add('other', $d['label'], $amount),
                    };
                }
                if (isset($er['GSIS'])) {
                    $add('gsis', 'Government share', (float) $er['GSIS']);
                    if (! $slip->run->isBonus() && ($slip->inputs['earned'] ?? 0) > 0) {
                        $cells['gsis'][$employeeId]['Basic salary'] = (float) $slip->inputs['earned'];
                    }
                }
                $add('philhealth', 'Employer share', (float) ($er['PhilHealth'] ?? 0));
                $add('pagibig', 'Employer share', (float) ($er['Pag-IBIG'] ?? 0));
                if ($slip->tax > 0 || isset($cells['bir'][$employeeId])) {
                    $add('bir', 'Gross pay', (float) $slip->gross);
                    $add('bir', 'Taxable pay', (float) $slip->taxable);
                }
            }
        }

        $out = [];
        foreach ($sections as $key => $s) {
            if (empty($cells[$key])) {
                continue;
            }
            $columns = [...$s['fixed'], ...array_keys($extra[$key] ?? [])];
            $columns = array_values(array_unique($columns));
            $rows = collect($cells[$key])->map(function ($values, $employeeId) use ($slips, $columns, $s) {
                $e = $slips[$employeeId]->first()->employee;

                return [
                    'employee_no' => $e->employee_no, 'name' => $e->full_name, 'id' => $s['id'] ? $e->{$s['id']} : null,
                    'values' => array_map(fn ($c) => round($values[$c] ?? 0, 2), $columns),
                ];
            })->sortBy('name')->values()->all();
            $totals = array_map(fn ($i) => round(array_sum(array_column(array_column($rows, 'values'), $i)), 2), array_keys($columns));
            $out[$key] = ['title' => $s['title'], 'id_label' => $s['id_label'], 'columns' => $columns, 'rows' => $rows, 'totals' => $totals];
        }

        return $out;
    }

    /** Amount to remit per section: everything except the informational salary/gross/taxable columns. */
    public static function remitTotal(array $section): float
    {
        $sum = 0.0;
        foreach ($section['columns'] as $i => $c) {
            if (! in_array($c, ['Basic salary', 'Gross pay', 'Taxable pay'], true)) {
                $sum += $section['totals'][$i];
            }
        }

        return round($sum, 2);
    }

    /** Payslips of a run as a bank credit list. */
    public function bankList(Collection $slips): array
    {
        return $slips->map(fn (Payslip $s) => [
            'account' => $s->employee->bank_account_no ? preg_replace('/\D/', '', $s->employee->bank_account_no) : null,
            'name' => $s->employee->full_name, 'employee_no' => $s->employee->employee_no, 'amount' => round($s->net, 2),
        ])->sortBy('name')->values()->all();
    }

    private function sectionFor(string $lender): string
    {
        return in_array($lender, ['gsis', 'pagibig', 'coop', 'bank'], true) ? $lender : 'other';
    }

    /** Loan columns are grouped by loan name, without the reference number. */
    private function column(string $label): string
    {
        return trim(preg_replace('/\s*\(#.*\)$/', '', $label));
    }
}
