<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\Payslip;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** Makes a run's draft payslips from attendance (PayrollInputs) and recomputes them after HR edits. */
class PayrollRunService
{
    public function __construct(private PayrollInputs $inputs, private PayCalculator $calc, private LoanService $loans) {}

    /** Creates or refreshes every draft payslip in the run. Lines HR added by hand are kept. Returns the count. */
    public function generate(PayrollRun $run): int
    {
        abort_if($run->isReleased(), 422, 'This payroll is already released.');
        if ($run->isBonus()) {
            return $this->generateBonus($run);
        }
        $month = $run->month();
        $rows = collect($this->inputs->rows($month->year, $month->month))->where('paid_by_agency', true);
        $employees = Employee::whereIn('employee_no', $rows->pluck('employee_no'))->get()->keyBy('employee_no');

        DB::transaction(function () use ($run, $rows, $employees) {
            $keep = [];
            foreach ($rows as $r) {
                $e = $employees[$r['employee_no']] ?? null;
                if (! $e) {
                    continue;
                }
                $slip = Payslip::firstOrNew(['payroll_run_id' => $run->id, 'employee_id' => $e->id]);
                $slip->inputs = $this->inputsFor($run, $e, $r);
                $this->compute($slip);
                $keep[] = $e->id;
            }
            $run->payslips()->whereNotIn('employee_id', $keep)->delete();
        });

        return $run->payslips()->count();
    }

    /** Recomputes totals, contributions and tax from the stored inputs and HR's manual lines. */
    public function compute(Payslip $slip, ?array $extraEarnings = null, ?array $extraDeductions = null): Payslip
    {
        $result = $this->calc->payslip($slip->inputs, $extraEarnings ?? $slip->manualEarnings(), $extraDeductions ?? $slip->manualDeductions());
        $slip->fill($result)->save();

        return $slip;
    }

    /**
     * Mid-year or year-end bonus: one month's basic salary (plus the cash gift at year-end) for
     * government personnel in service at least 4 months as of the cut-off.
     */
    private function generateBonus(PayrollRun $run): int
    {
        $cfg = config("hris.payroll.bonuses.{$run->cut}");
        $year = $run->month()->year;
        $cutoff = CarbonImmutable::parse("$year-{$cfg['cutoff']}");
        $members = config('hris.payroll.gsis_members');

        $eligible = Employee::where('is_active', true)->whereIn('employment_type', $members)->where('monthly_salary', '>', 0)->get()
            ->filter(fn (Employee $e) => ! $e->date_hired || $e->date_hired->lte($cutoff->subMonths($cfg['min_months'])))
            ->filter(fn (Employee $e) => ! $e->date_separated || $e->date_separated->gte($cutoff));

        DB::transaction(function () use ($run, $eligible, $cfg, $cutoff) {
            $keep = [];
            foreach ($eligible as $e) {
                $lines = [['label' => $cfg['label'].' (one month basic salary)', 'amount' => (float) $e->monthly_salary]];
                if ($cfg['cash_gift'] > 0) {
                    $lines[] = ['label' => 'Cash gift', 'amount' => (float) $cfg['cash_gift']];
                }
                $slip = Payslip::firstOrNew(['payroll_run_id' => $run->id, 'employee_id' => $e->id]);
                $slip->inputs = [
                    'bonus' => true, 'lines' => $lines, 'cutoff' => $cutoff->toDateString(),
                    'position' => $e->position, 'office' => $e->office, 'type_label' => $e->type_label,
                    'salary_grade' => $e->salary_grade, 'step' => $e->step,
                ];
                $this->compute($slip);
                $keep[] = $e->id;
            }
            $run->payslips()->whereNotIn('employee_id', $keep)->delete();
        });

        return $run->payslips()->count();
    }

    private function inputsFor(PayrollRun $run, Employee $e, array $r): array
    {
        $daily = $r['basis'] === 'daily';
        $salary = $daily ? (float) $r['rate'] * (float) ($r['days_paid'] ?? 0) : (float) $e->monthly_salary;
        $allowances = collect(config('hris.payroll.allowances', []))
            ->filter(fn ($a) => in_array($e->employment_type, $a['types'] ?? [], true))
            ->map(fn ($a) => ['label' => $a['label'], 'amount' => (float) $a['amount'], 'taxable' => (bool) $a['taxable']])->values()->all();

        return [
            'basis' => $r['basis'],
            'basic_label' => $daily ? 'Basic pay ('.($r['days_paid'] ?? 0).' days × ₱'.number_format((float) $r['rate'], 2).')' : 'Basic salary',
            'salary' => round($salary, 2),
            'attendance_deduction' => (float) $r['deductions'],
            'earned' => (float) $r['monthly_basic'],
            'gsis_member' => (bool) $r['gsis_member'],
            'contributions' => (bool) $r['gsis_member'],
            'divisor' => $run->cut === 'full' ? 1 : 2,
            'allowances' => $allowances,
            'loans' => $this->loans->linesFor($run, $e),
            'rate' => $r['rate'],
            'days_present' => $r['days_present'],
            'days_absent' => $r['days_absent'],
            'leave_days' => $r['leave_days'],
            'lwop_days' => $r['lwop_days'],
            'undertime_minutes' => $r['undertime_minutes'],
            'salary_grade' => $e->salary_grade,
            'step' => $e->step,
            'position' => $e->position,
            'office' => $e->office,
            'type_label' => $e->type_label,
        ];
    }
}
