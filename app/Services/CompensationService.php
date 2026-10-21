<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\Ipcr;
use App\Models\SalaryNotice;
use App\Models\SalarySchedule;
use App\Models\ServiceRecord;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Step increments and salary adjustments.
 *
 * Step increment (CSC-DBM Joint Circular No. 1, s. 2012): one step for every 3 years of continuous
 * satisfactory service in the present position, up to step 8. Counted from the date the current step
 * took effect (step date), or the date hired. An Unsatisfactory or Poor final IPCR rating in those
 * 3 years holds it back. Salary adjustment: a new salary schedule (tranche) applied to everyone's
 * grade and step.
 */
class CompensationService
{
    public const YEARS_PER_STEP = 3;

    public const MAX_STEP = 8;

    /** Types that receive step increments and salary adjustments (plantilla positions with a grade). */
    public function covered(): Collection
    {
        return Employee::where('is_active', true)->whereNotNull('salary_grade')->orderBy('last_name')->orderBy('first_name')->get()
            ->filter(fn (Employee $e) => $e->rules()['plantilla'] ?? false)->values();
    }

    /**
     * Who is due (or will be within $days) a step increment.
     *
     * @return Collection<int, array{employee: Employee, base: ?CarbonImmutable, due: ?CarbonImmutable, new_step: int, current: float, new_salary: ?float, blocker: ?string}>
     */
    public function stepIncrements(int $days = 60): Collection
    {
        $limit = today()->addDays($days);

        return $this->covered()->map(function (Employee $e) {
            $base = ($e->step_date ?? $e->date_hired)?->toImmutable();
            $step = max(1, (int) $e->step);
            $due = $base?->addYears(self::YEARS_PER_STEP);
            $schedule = $due ? SalarySchedule::inEffect($due) : null;

            return [
                'employee' => $e,
                'base' => $base,
                'due' => $due,
                'new_step' => min(self::MAX_STEP, $step + 1),
                'current' => (float) $e->monthly_salary,
                'new_salary' => $schedule?->amount($e->salary_grade, $step + 1),
                'blocker' => $this->blocker($e, $base, $due, $step, $schedule),
            ];
        })->filter(fn ($r) => $r['due'] && $r['due']->lte($limit) && $r['employee']->step < self::MAX_STEP)->sortBy('due')->values();
    }

    private function blocker(Employee $e, ?CarbonImmutable $base, ?CarbonImmutable $due, int $step, ?SalarySchedule $schedule): ?string
    {
        if (! $base) {
            return 'No step date or date hired on the 201 file.';
        }
        if ($step >= self::MAX_STEP) {
            return 'Already at step 8.';
        }
        $poor = Ipcr::where('employee_id', $e->id)->whereNotNull('finalized_at')->whereIn('adjectival', ['Unsatisfactory', 'Poor'])
            ->whereHas('period', fn ($q) => $q->whereDate('ends_on', '>=', $base->toDateString())->whereDate('starts_on', '<=', $due->toDateString()))
            ->with('period')->first();
        if ($poor) {
            return "{$poor->adjectival} rating ({$poor->period->name}) within the 3 years.";
        }
        if (! $schedule) {
            return 'No salary schedule in effect on the due date. Add one under Salary schedules.';
        }
        if ($schedule->amount($e->salary_grade, $step + 1) === null) {
            return "Salary schedule “{$schedule->name}” has no amount for SG {$e->salary_grade} step ".($step + 1).'.';
        }

        return null;
    }

    /** Grants the next step from $effective: new salary, NOSI, service record. */
    public function grantStep(Employee $e, CarbonInterface $effective, User $by): SalaryNotice
    {
        $step = max(1, (int) $e->step);
        if ($step >= self::MAX_STEP) {
            throw ValidationException::withMessages(['employee' => "{$e->full_name} is already at step 8."]);
        }
        $schedule = SalarySchedule::inEffect($effective);
        $amount = $schedule?->amount($e->salary_grade, $step + 1);
        if ($amount === null) {
            throw ValidationException::withMessages(['employee' => "No salary for SG {$e->salary_grade} step ".($step + 1).' in the schedule in effect on '.$effective->format('M j, Y').'.']);
        }

        return $this->change($e, 'nosi', $effective, $e->salary_grade, $step + 1, $amount, $schedule,
            'Step increment for '.self::YEARS_PER_STEP.' years of continuous satisfactory service (CSC-DBM JC No. 1, s. 2012)', $by);
    }

    /**
     * Everyone whose salary changes under a schedule.
     *
     * @return Collection<int, array{employee: Employee, old: float, new: ?float}>
     */
    public function adjustments(SalarySchedule $schedule): Collection
    {
        return $this->covered()->map(fn (Employee $e) => [
            'employee' => $e, 'old' => (float) $e->monthly_salary, 'new' => $schedule->amount($e->salary_grade, max(1, (int) $e->step)),
        ])->values();
    }

    /** Applies a new salary schedule: NOSA for everyone whose pay changes. Returns how many. */
    public function applySchedule(SalarySchedule $schedule, User $by): int
    {
        $count = 0;
        DB::transaction(function () use ($schedule, $by, &$count) {
            foreach ($this->adjustments($schedule) as $row) {
                if ($row['new'] === null || abs($row['new'] - $row['old']) < 0.005) {
                    continue;
                }
                $already = SalaryNotice::where('employee_id', $row['employee']->id)->where('type', 'nosa')->where('salary_schedule_id', $schedule->id)->exists();
                if ($already) {
                    continue;
                }
                $e = $row['employee'];
                $this->change($e, 'nosa', $schedule->effective_date, $e->salary_grade, max(1, (int) $e->step), $row['new'], $schedule,
                    $schedule->basis ?: 'Salary schedule '.$schedule->name, $by, keepStepDate: true);
                $count++;
            }
        });
        AuditLog::record('compensation.schedule_applied', $schedule, ['notices' => $count]);

        return $count;
    }

    private function change(Employee $e, string $type, CarbonInterface $effective, ?int $grade, int $step, float $amount, ?SalarySchedule $schedule, string $basis, User $by, bool $keepStepDate = false): SalaryNotice
    {
        return DB::transaction(function () use ($e, $type, $effective, $grade, $step, $amount, $schedule, $basis, $by, $keepStepDate) {
            $notice = SalaryNotice::create([
                'employee_id' => $e->id, 'type' => $type, 'effective_date' => $effective->toDateString(),
                'old_grade' => $e->salary_grade, 'old_step' => $e->step, 'old_salary' => $e->monthly_salary,
                'new_grade' => $grade, 'new_step' => $step, 'new_salary' => $amount,
                'basis' => $basis, 'salary_schedule_id' => $schedule?->id, 'created_by' => $by->id,
            ]);

            // Service record: close the open line the day before and start a new one.
            $open = ServiceRecord::where('employee_id', $e->id)->whereNull('date_to')->orderByDesc('date_from')->first();
            if ($open && $open->date_from->lt($effective)) {
                $open->update(['date_to' => $effective->copy()->subDay()->toDateString()]);
            }
            ServiceRecord::create([
                'employee_id' => $e->id, 'date_from' => $effective->toDateString(), 'designation' => $e->position ?? $open?->designation ?? '',
                'status' => $open?->status ?? $e->type_label, 'annual_salary' => round($amount * 12, 2), 'office' => $open?->office ?? $e->office,
                'branch' => $open?->branch ?? 'National', 'remarks' => $type === 'nosi' ? 'Step increment' : 'Salary adjustment',
            ]);

            $e->forceFill(['step' => $step, 'monthly_salary' => $amount] + ($keepStepDate ? [] : ['step_date' => $effective->toDateString()]))->save();
            AuditLog::record("compensation.{$type}", $e, ['from' => $notice->old_salary, 'to' => $amount, 'effective' => $effective->toDateString()]);

            return $notice;
        });
    }
}
