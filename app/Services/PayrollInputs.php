<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\LeaveApplication;
use Carbon\CarbonImmutable;

/**
 * Monthly payroll inputs from attendance, in the shape rdcruz-ph-payroll's computePay() takes.
 *
 * - Monthly-paid (plantilla, casual, contractual): monthly salary less leave without pay, unauthorized
 *   absences and undertime not covered by VL, at monthly ÷ working days per day.
 * - Daily-paid (COS, JO): days actually worked (OB/travel counts as worked) × daily rate, less undertime hours.
 * - Service providers and DBP personnel are paid by their own agency: attendance only, no pay lines.
 */
class PayrollInputs
{
    public function __construct(private DtrBuilder $dtr, private LeaveService $leaves) {}

    public function rows(int $year, int $month): array
    {
        $cfg = config('hris.payroll');
        $start = CarbonImmutable::create($year, $month, 1);
        $end = $start->endOfMonth();

        return Employee::where('is_active', true)->orderBy('last_name')->orderBy('first_name')->get()
            ->map(function (Employee $e) use ($cfg, $year, $month, $start, $end) {
                $d = $this->dtr->build($e, $year, $month);
                $paid = in_array($e->employment_type, $cfg['paid_by_agency'], true);
                $daily = in_array($e->employment_type, ['cos', 'job_order'], true);
                $row = [
                    'employee_no' => $e->employee_no,
                    'name' => $e->full_name,
                    'type' => $e->employment_type,
                    'type_label' => $e->type_label,
                    'office' => $e->office,
                    'paid_by_agency' => $paid,
                    'gsis_member' => in_array($e->employment_type, $cfg['gsis_members'], true),
                    'basis' => $daily ? 'daily' : 'monthly',
                    'days_present' => $d['days_present'],
                    'travel_days' => $d['travel_days'],
                    'leave_days' => $d['leave_days'],
                    'days_absent' => $d['days_absent'],
                    'suspended_days' => $d['suspended_days'],
                    'undertime_minutes' => $d['total_minutes'],
                    'incomplete_days' => $d['incomplete_days'],
                    'overtime_credited_minutes' => $d['overtime']['credited'],
                    'lwop_days' => 0.0,
                    'rate' => null,
                    'deductions' => 0.0,
                    'monthly_basic' => 0.0,
                    'notes' => [],
                ];

                if (! $paid) {
                    $row['notes'][] = 'Paid by '.($e->agency_name ?: 'own agency').'; attendance only';

                    return $row;
                }

                if ($daily) {
                    $rate = (float) ($e->daily_rate ?: ($e->monthly_salary ? $e->monthly_salary / $cfg['working_days'] : 0));
                    // Wellness leave is with pay, so those days are paid like days worked.
                    $wellness = $this->paidLeaveDays($e, 'WL', $start, $end);
                    $days = $d['days_present'] + $d['travel_days'] + (fmod($wellness, 1.0) == 0.0 ? (int) $wellness : $wellness)
                        + ($d['suspended_days'] - $d['suspended_unpaid_days']);
                    $row['wellness_days'] = $wellness;
                    $utPay = round($rate / 8 * $d['total_minutes'] / 60, 2);
                    $row['rate'] = round($rate, 2);
                    $row['deductions'] = $utPay;
                    $row['monthly_basic'] = round(max(0, $rate * $days - $utPay), 2);
                    $row['days_paid'] = $days;
                    if (! $rate) {
                        $row['notes'][] = 'No daily rate on file';
                    }
                } else {
                    $monthly = (float) $e->monthly_salary;
                    $rate = $monthly / $cfg['working_days'];
                    $lwop = $this->lwopDays($e, $start, $end);
                    // Undertime is charged to VL; only what VL can't cover is deducted from pay.
                    $utDays = $d['total_minutes'] / config('hris.minutes_per_day');
                    $vl = $e->earnsLeave() ? $this->leaves->balances($e)['VL'] : 0;
                    $utNoPay = $e->earnsLeave() ? min($utDays, max(0, -$vl)) : $utDays;
                    $deductDays = $lwop + $d['days_absent'] + $utNoPay + $d['suspended_unpaid_days'];
                    $row['rate'] = round($rate, 2);
                    $row['lwop_days'] = round($lwop, 3);
                    $row['undertime_without_pay_days'] = round($utNoPay, 3);
                    $row['deductions'] = round($rate * $deductDays, 2);
                    $row['monthly_basic'] = round(max(0, $monthly - $row['deductions']), 2);
                    if (! $monthly) {
                        $row['notes'][] = 'No monthly salary on file';
                    }
                }
                if ($d['suspended_days']) {
                    $row['notes'][] = $d['suspended_days'].' day(s) suspended'.($d['suspended_unpaid_days'] ? ' ('.$d['suspended_unpaid_days'].' without pay)' : ' (with pay)');
                }
                if ($d['incomplete_days']) {
                    $row['notes'][] = $d['incomplete_days'].' day(s) with a missing time-in/out';
                }

                return $row;
            })->values()->all();
    }

    /** Input for rdcruz-ph-payroll computePay(), one per paid employee. */
    public function computePayInputs(array $rows, string $frequency = 'monthly'): array
    {
        $cfg = config('hris.payroll');

        return collect($rows)->where('paid_by_agency', true)->map(fn ($r) => [
            'employee_no' => $r['employee_no'],
            'name' => $r['name'],
            'computePay' => ['monthlyBasic' => $r['monthly_basic'], 'frequency' => $frequency, 'taxableAllowances' => 0, 'nonTaxable' => 0, 'otherDeductions' => 0],
            // Government employees pay GSIS instead of SSS: use philhealth(), pagibig() and withholdingTax() from the library with these.
            'gsis' => $r['gsis_member'] ? ['employee' => round($r['monthly_basic'] * $cfg['gsis_employee'], 2), 'employer' => round($r['monthly_basic'] * $cfg['gsis_employer'], 2)] : null,
        ])->values()->all();
    }

    /** Approved days of a leave type that fall in the month. */
    private function paidLeaveDays(Employee $e, string $type, CarbonImmutable $start, CarbonImmutable $end): float
    {
        return (float) LeaveApplication::where('employee_id', $e->id)->where('status', 'approved')->where('type', $type)
            ->whereDate('date_from', '<=', $end->toDateString())->whereDate('date_to', '>=', $start->toDateString())->get()
            ->sum(fn (LeaveApplication $l) => $this->leaves->workingDays(
                max($l->date_from->toDateString(), $start->toDateString()), min($l->date_to->toDateString(), $end->toDateString()), $l->half_day));
    }

    /** Days of leave without pay that fall in the month (applications spanning months are split by work day). */
    private function lwopDays(Employee $e, CarbonImmutable $start, CarbonImmutable $end): float
    {
        return LeaveApplication::where('employee_id', $e->id)->where('status', 'approved')->where('days_without_pay', '>', 0)
            ->whereDate('date_from', '<=', $end->toDateString())->whereDate('date_to', '>=', $start->toDateString())->get()
            ->sum(function (LeaveApplication $l) use ($start, $end) {
                $from = max($l->date_from->toDateString(), $start->toDateString());
                $to = min($l->date_to->toDateString(), $end->toDateString());
                $inMonth = $this->leaves->workingDays($from, $to, $l->half_day);

                return $l->days > 0 ? $l->days_without_pay * ($inMonth / $l->days) : 0;
            });
    }
}
