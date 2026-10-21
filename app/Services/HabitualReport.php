<?php

namespace App\Services;

use App\Models\Employee;
use Illuminate\Support\Collection;

/**
 * CSC habitual tardiness and habitual absenteeism, from the DTR.
 *
 * Habitually tardy: tardy 10 or more times a month (regardless of minutes), for at least 2 months
 * in a semester or at least 2 consecutive months during the year.
 * Habitually absent: unauthorized absences over 2.5 days a month (the monthly leave credit),
 * for at least 3 months in a semester or at least 3 consecutive months during the year.
 */
class HabitualReport
{
    public function __construct(private DtrBuilder $dtr) {}

    /**
     * @param  Collection<int, Employee>  $employees
     * @return Collection<int, array{employee: Employee, months: array<int, array{tardy: int, absent: float}>, tardy: ?string, absent: ?string}>
     */
    public function forYear(int $year, Collection $employees): Collection
    {
        $lastMonth = $year < now()->year ? 12 : ($year > now()->year ? 0 : now()->month);

        return $employees->map(function (Employee $employee) use ($year, $lastMonth) {
            $months = [];
            $start = $employee->date_hired ?? $employee->contract_start;
            for ($m = 1; $m <= $lastMonth; $m++) {
                if ($start && $start->gt(now()->setDate($year, $m, 1)->endOfMonth())) {
                    continue; // not yet hired that month
                }
                $r = $this->dtr->build($employee, $year, $m);
                $months[$m] = ['tardy' => $r['tardy_days'], 'absent' => (float) $r['days_absent']];
            }

            return [
                'employee' => $employee,
                'months' => $months,
                'tardy' => $this->flag(array_map(fn ($x) => $x['tardy'] >= config('hris.habitual.tardy_per_month'), $months), config('hris.habitual.tardy_months')),
                'absent' => $this->flag(array_map(fn ($x) => $x['absent'] > config('hris.habitual.absent_per_month'), $months), config('hris.habitual.absent_months')),
            ];
        });
    }

    /**
     * Returns why the rule is met, or null. $hits is month number => over the monthly limit.
     *
     * @param  array<int, bool>  $hits
     */
    public function flag(array $hits, int $needed): ?string
    {
        $names = fn (array $ms) => collect($ms)->map(fn ($m) => date('M', mktime(0, 0, 0, $m, 1)))->join(', ');

        foreach ([1 => range(1, 6), 2 => range(7, 12)] as $sem => $range) {
            $inSemester = array_values(array_filter($range, fn ($m) => ! empty($hits[$m])));
            if (count($inSemester) >= $needed) {
                return "{$needed}+ months in the ".($sem === 1 ? '1st' : '2nd').' semester ('.$names($inSemester).')';
            }
        }

        $run = [];
        for ($m = 1; $m <= 12; $m++) {
            $run = ! empty($hits[$m]) ? [...$run, $m] : [];
            if (count($run) >= $needed) {
                return "{$needed} consecutive months (".$names($run).')';
            }
        }

        return null;
    }
}
