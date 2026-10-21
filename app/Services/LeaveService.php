<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveApplication;
use App\Models\LeaveLedger;
use App\Models\Setting;
use App\Models\User;
use App\Support\Notify;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Leave credits and applications (CSC rules):
 * - 1.25 VL + 1.25 SL earned per month by those with leave credits
 * - Tardiness/undertime charged to VL (8 hours = 1 day)
 * - 3 SPL days a year, non-cumulative; 5 days forced leave a year when VL ≥ 10
 * - COC (hours) earned from credited overtime, used through CTO
 * - Leave without enough credits is approved as part with pay, part without pay
 */
class LeaveService
{
    public function __construct(private DtrBuilder $dtr) {}

    /** Work days between two dates (weekends and holidays excluded), or 0.5 for a half day. */
    public function workingDays(string $from, string $to, ?string $halfDay = null): float
    {
        $holidays = Holiday::whereDate('date', '>=', $from)->whereDate('date', '<=', $to)->get()
            ->map(fn ($h) => $h->date->toDateString())->all();
        $workSat = Setting::get('work_saturday') === '1';
        $days = 0;
        for ($d = CarbonImmutable::parse($from); $d->lte(CarbonImmutable::parse($to)); $d = $d->addDay()) {
            if ($d->isSunday() || ($d->isSaturday() && ! $workSat) || in_array($d->toDateString(), $holidays, true)) {
                continue;
            }
            $days++;
        }

        return $halfDay && $days === 1 ? 0.5 : (float) $days;
    }

    /** @return array<string, float> balance per VL, SL, SPL, COC (COC in hours) */
    public function balances(Employee $employee): array
    {
        $rows = LeaveLedger::where('employee_id', $employee->id)
            ->selectRaw('balance, sum(earned) - sum(used) as total')->groupBy('balance')->pluck('total', 'balance');
        $out = [];
        foreach (array_keys(config('hris.balances')) as $key) {
            $out[$key] = round((float) ($rows[$key] ?? 0), 3);
        }

        return $out;
    }

    /** Days already approved (or awaiting approval) of a type in a year. */
    public function usedThisYear(Employee $employee, string $type, int $year, ?int $exceptId = null): float
    {
        return (float) LeaveApplication::where('employee_id', $employee->id)->where('type', $type)
            ->whereIn('status', ['pending', 'recommended', 'approved'])
            ->whereYear('date_from', $year)
            ->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))
            ->sum('days');
    }

    public function wellnessEligible(Employee $employee): bool
    {
        return in_array($employee->employment_type, config('hris.wellness.types'), true);
    }

    /** Wellness leave days for the year: 5 if engaged before the year, 2 if from January to June, else 0. */
    public function wellnessEntitlement(Employee $employee, int $year): float
    {
        if (! $this->wellnessEligible($employee)) {
            return 0.0;
        }
        $cfg = config('hris.wellness');
        $start = $employee->date_hired ?? $employee->contract_start;
        if (! $start || $start->year < $year) {
            return (float) $cfg['full_days'];
        }
        if ($start->year > $year) {
            return 0.0;
        }

        return $start->month <= $cfg['new_until_month'] ? (float) $cfg['new_days'] : 0.0;
    }

    /** @return array{entitled: float, used: float, remaining: float} including applications still waiting for approval */
    public function wellness(Employee $employee, int $year, ?int $exceptId = null): array
    {
        $entitled = $this->wellnessEntitlement($employee, $year);
        $used = $this->usedThisYear($employee, 'WL', $year, $exceptId);

        return ['entitled' => $entitled, 'used' => $used, 'remaining' => max(0, $entitled - $used)];
    }

    public function file(Employee $employee, array $data, User $by): LeaveApplication
    {
        $type = $data['type'];
        $rule = config("hris.leave_types.$type");
        if (! $rule) {
            throw ValidationException::withMessages(['type' => 'Unknown leave type.']);
        }
        if (! empty($rule['wellness'])) {
            if (! $this->wellnessEligible($employee)) {
                throw ValidationException::withMessages(['type' => 'Wellness leave is for contract of service and service provider personnel.']);
            }
            if (substr($data['date_from'], 0, 4) !== substr($data['date_to'], 0, 4)) {
                throw ValidationException::withMessages(['date_to' => 'File wellness leave separately for each year.']);
            }
            $year = (int) substr($data['date_from'], 0, 4);
            $w = $this->wellness($employee, $year);
            $days = $this->workingDays($data['date_from'], $data['date_to'], $data['date_from'] === $data['date_to'] ? ($data['half_day'] ?? null) : null);
            if ($days > $w['remaining']) {
                throw ValidationException::withMessages(['type' => $w['entitled'] == 0
                    ? "You have no wellness leave for $year yet (engaged from July onwards). It starts next year."
                    : "You have {$w['remaining']} wellness leave day(s) left for $year (of {$w['entitled']})."]);
            }
        }
        if ($rule['charge'] && ! $employee->earnsLeave()) {
            throw ValidationException::withMessages(['type' => $employee->type_label.' personnel have no leave credits. File it as "Others" so the days are excused on the DTR.']);
        }

        $halfDay = $data['date_from'] === $data['date_to'] ? ($data['half_day'] ?? null) : null;
        $days = $this->workingDays($data['date_from'], $data['date_to'], $halfDay);
        if ($days <= 0) {
            throw ValidationException::withMessages(['date_from' => 'Those dates have no work days.']);
        }

        $overlap = LeaveApplication::where('employee_id', $employee->id)->whereIn('status', ['pending', 'recommended', 'approved'])
            ->whereDate('date_from', '<=', $data['date_to'])->whereDate('date_to', '>=', $data['date_from'])->exists();
        if ($overlap) {
            throw ValidationException::withMessages(['date_from' => 'You already have a leave application on those dates.']);
        }

        if ($rule['max'] && empty($rule['wellness']) && $this->usedThisYear($employee, $type, (int) substr($data['date_from'], 0, 4)) + $days > $rule['max']) {
            throw ValidationException::withMessages(['type' => "{$rule['name']} is limited to {$rule['max']} days a year."]);
        }

        // SL filed in advance is allowed only for scheduled check-ups/operations, so we just warn via details.
        $application = LeaveApplication::create([
            'employee_id' => $employee->id,
            'type' => $type,
            'date_from' => $data['date_from'],
            'date_to' => $data['date_to'],
            'half_day' => $halfDay,
            'days' => $days,
            'details' => array_filter($data['details'] ?? []),
            'commutation' => (bool) ($data['commutation'] ?? false),
            'reason' => $data['reason'] ?? null,
            'status' => 'pending',
            'filed_by' => $by->id,
        ]);
        AuditLog::record('leave.filed', $application, ['type' => $type, 'days' => $days]);
        Notify::approver($employee, "Leave application: {$employee->display_name}", [
            "{$employee->display_name} filed {$application->type_name} for {$days} day(s), ".$this->period($application).'.',
            $application->reason ? 'Reason: '.$application->reason : 'Please recommend or disapprove it on your Approvals page.',
        ]);

        return $application;
    }

    public function recommend(LeaveApplication $leave, User $by, bool $approve, ?string $remarks = null): void
    {
        abort_unless($leave->status === 'pending', 422, 'This application is no longer pending.');
        $leave->update($approve
            ? ['status' => 'recommended', 'recommended_by' => $by->id, 'recommended_at' => now(), 'remarks' => $remarks]
            : ['status' => 'disapproved', 'recommended_by' => $by->id, 'recommended_at' => now(), 'remarks' => $remarks]);
        AuditLog::record($approve ? 'leave.recommended' : 'leave.disapproved', $leave, ['remarks' => $remarks]);
        $approve
            ? Notify::hr("Leave for approval: {$leave->employee->display_name}", ["{$leave->type_name}, ".$this->period($leave).", recommended by {$by->name}."], route('admin.leave.index', ['status' => 'recommended']))
            : Notify::employee($leave->employee, 'Your leave application was disapproved', ["{$leave->type_name}, ".$this->period($leave).'.', 'Reason: '.$remarks], route('me.leave'));
    }

    /** Final approval by HR: charges the credits (with pay up to the balance, the rest without pay). */
    public function approve(LeaveApplication $leave, User $by, ?string $remarks = null): void
    {
        abort_unless($leave->isOpen(), 422, 'This application is no longer open.');

        DB::transaction(function () use ($leave, $by, $remarks) {
            $employee = $leave->employee;
            $charge = config("hris.leave_types.{$leave->type}.charge");
            $withPay = $leave->days;
            $withoutPay = 0.0;

            if ($charge && $employee->earnsLeave()) {
                $balance = $this->balances($employee)[$charge] ?? 0;
                $needed = $charge === 'COC' ? $leave->days * 8 : $leave->days; // COC in hours
                $chargeable = max(0, min($needed, $balance));
                $withPay = $charge === 'COC' ? $chargeable / 8 : $chargeable;
                $withoutPay = round($leave->days - $withPay, 3);

                if ($chargeable > 0) {
                    LeaveLedger::create([
                        'employee_id' => $employee->id, 'balance' => $charge, 'entry_date' => $leave->date_from,
                        'particulars' => $leave->type_name.' '.$this->period($leave), 'used' => $chargeable,
                        'source' => 'leave', 'source_id' => $leave->id, 'created_by' => $by->id,
                    ]);
                }
            }

            $leave->update([
                'status' => 'approved', 'approved_by' => $by->id, 'approved_at' => now(),
                'days_with_pay' => round($withPay, 3), 'days_without_pay' => $withoutPay,
                'remarks' => $remarks ?? $leave->remarks,
            ]);
            AuditLog::record('leave.approved', $leave, ['with_pay' => $withPay, 'without_pay' => $withoutPay]);
            Notify::employee($employee, 'Your leave is approved', [
                "{$leave->type_name}, ".$this->period($leave).'.',
                $withoutPay > 0 ? "Approved for {$withPay} day(s) with pay and {$withoutPay} day(s) without pay (not enough credits)." : "Approved for {$withPay} day(s) with pay.",
            ], route('me.leave'));
        });
    }

    public function disapprove(LeaveApplication $leave, User $by, string $remarks): void
    {
        abort_unless($leave->isOpen(), 422, 'This application is no longer open.');
        $leave->update(['status' => 'disapproved', 'approved_by' => $by->id, 'approved_at' => now(), 'remarks' => $remarks]);
        AuditLog::record('leave.disapproved', $leave, ['remarks' => $remarks]);
        Notify::employee($leave->employee, 'Your leave application was disapproved', ["{$leave->type_name}, ".$this->period($leave).'.', 'Reason: '.$remarks], route('me.leave'));
    }

    /** Cancel an open application, or an approved one (credits are restored). */
    public function cancel(LeaveApplication $leave, User $by, ?string $remarks = null): void
    {
        abort_if(in_array($leave->status, ['cancelled', 'disapproved'], true), 422, 'Already closed.');
        DB::transaction(function () use ($leave, $by, $remarks) {
            LeaveLedger::where('source', 'leave')->where('source_id', $leave->id)->delete();
            $leave->update(['status' => 'cancelled', 'remarks' => $remarks ?? $leave->remarks]);
            AuditLog::record('leave.cancelled', $leave, ['by' => $by->id]);
        });
    }

    /**
     * Month-end posting for everyone with leave credits: 1.25 VL/SL earned, tardiness/undertime charged
     * to VL, and COC hours from credited overtime. Safe to run again: the month's postings are replaced.
     *
     * @return array{employees: int, accrued: int, undertime: int, coc: int}
     */
    public function postMonth(int $year, int $month, ?User $by = null): array
    {
        $period = sprintf('%04d-%02d', $year, $month);
        $end = CarbonImmutable::create($year, $month, 1)->endOfMonth()->startOfDay();
        $label = $end->format('F Y');
        $stats = ['employees' => 0, 'accrued' => 0, 'undertime' => 0, 'coc' => 0];

        $employees = Employee::where('is_active', true)->get()
            ->filter(fn (Employee $e) => $e->earnsLeave() || $e->overtimeEligible());

        DB::transaction(function () use ($employees, $period, $end, $label, $year, $month, $by, &$stats) {
            LeaveLedger::whereIn('source', ['accrual', 'undertime', 'coc'])->where('period', $period)
                ->whereIn('employee_id', $employees->pluck('id'))->delete();

            foreach ($employees as $e) {
                $stats['employees']++;
                $base = ['employee_id' => $e->id, 'entry_date' => $end, 'period' => $period, 'created_by' => $by?->id];
                $dtr = $this->dtr->build($e, $year, $month);

                if ($e->earnsLeave() && (! $e->date_hired || $e->date_hired->lte($end))) {
                    foreach (config('hris.monthly_accrual') as $bal => $days) {
                        LeaveLedger::create($base + ['balance' => $bal, 'particulars' => "Earned $label", 'earned' => $days, 'source' => 'accrual']);
                    }
                    $stats['accrued']++;

                    if ($dtr['total_minutes'] > 0) {
                        LeaveLedger::create($base + [
                            'balance' => 'VL', 'source' => 'undertime',
                            'particulars' => sprintf('Tardiness/undertime %s (%dh %02dm)', $label, intdiv($dtr['total_minutes'], 60), $dtr['total_minutes'] % 60),
                            'used' => round($dtr['total_minutes'] / config('hris.minutes_per_day'), 3),
                        ]);
                        $stats['undertime']++;
                    }
                }

                if ($e->overtimeEligible() && $dtr['overtime_rules']['coc'] && $dtr['overtime']['coc_capped'] > 0) {
                    LeaveLedger::create($base + [
                        'balance' => 'COC', 'source' => 'coc', 'particulars' => "COC earned $label",
                        'earned' => round($dtr['overtime']['coc_capped'] / 60, 3),
                    ]);
                    $stats['coc']++;
                }
            }

            // COC earned N months ago and still unused lapses now.
            $lapseMonth = $end->startOfMonth()->subMonthsNoOverflow(config('hris.coc_validity_months'));
            foreach (Employee::where('is_active', true)->get() as $e) {
                if ($this->expireCoc($e, $lapseMonth, $end, $by)) {
                    $stats['coc_lapsed'] = ($stats['coc_lapsed'] ?? 0) + 1;
                }
            }
        });
        AuditLog::record('leave.month_posted', null, ['period' => $period] + $stats);

        return $stats;
    }

    /**
     * Lapses the unused part of the COC earned in $month (oldest COC is used first). Re-running replaces it.
     */
    public function expireCoc(Employee $employee, CarbonImmutable $month, CarbonImmutable $asOf, ?User $by = null): float
    {
        $period = $month->format('Y-m');
        LeaveLedger::where('employee_id', $employee->id)->where('source', 'coc_lapse')->where('period', $period)->delete();

        $rows = LeaveLedger::where('employee_id', $employee->id)->where('balance', 'COC')->orderBy('entry_date')->orderBy('id')->get();
        $earnedBefore = $rows->where('earned', '>', 0)->filter(fn ($r) => $r->entry_date->lt($month->startOfMonth()))->sum('earned');
        $earnedInMonth = $rows->where('earned', '>', 0)->filter(fn ($r) => $r->entry_date->format('Y-m') === $period)->sum('earned');
        if ($earnedInMonth <= 0) {
            return 0;
        }
        // Everything used or lapsed so far is taken from the oldest COC first.
        $consumed = $rows->sum('used');
        $left = max(0, $earnedInMonth - max(0, $consumed - $earnedBefore));
        if ($left <= 0) {
            return 0;
        }

        LeaveLedger::create([
            'employee_id' => $employee->id, 'balance' => 'COC', 'entry_date' => $asOf, 'period' => $period,
            'particulars' => 'COC earned '.$month->format('F Y').' lapsed', 'used' => round($left, 3),
            'source' => 'coc_lapse', 'created_by' => $by?->id,
        ]);

        return $left;
    }

    /**
     * Year-end: forced leave not taken is deducted from VL (when VL ≥ 10), unused SPL lapses, and next
     * year's 3 SPL days are granted. Safe to run again for the same year.
     */
    public function postYearEnd(int $year, ?User $by = null): array
    {
        $period = (string) $year;
        $stats = ['forced' => 0, 'spl' => 0];
        $date = CarbonImmutable::create($year, 12, 31);

        DB::transaction(function () use ($year, $period, $date, $by, &$stats) {
            LeaveLedger::whereIn('source', ['forced', 'lapse', 'spl_grant'])->where('period', $period)->delete();

            foreach (Employee::where('is_active', true)->get()->filter->earnsLeave() as $e) {
                $base = ['employee_id' => $e->id, 'period' => $period, 'created_by' => $by?->id];
                $bal = $this->balances($e);
                $forcedTaken = (float) LeaveApplication::where('employee_id', $e->id)->where('status', 'approved')
                    ->whereIn('type', ['FL', 'VL'])->whereYear('date_from', $year)->sum('days_with_pay');
                $missing = max(0, 5 - $forcedTaken);
                if ($bal['VL'] >= 10 && $missing > 0) {
                    LeaveLedger::create($base + ['balance' => 'VL', 'entry_date' => $date, 'source' => 'forced',
                        'particulars' => "Forced leave not taken $year", 'used' => $missing]);
                    $stats['forced']++;
                }
                if ($bal['SPL'] > 0) {
                    LeaveLedger::create($base + ['balance' => 'SPL', 'entry_date' => $date, 'source' => 'lapse',
                        'particulars' => "SPL lapsed $year", 'used' => $bal['SPL']]);
                }
                LeaveLedger::create($base + ['balance' => 'SPL', 'entry_date' => $date->addDay(), 'source' => 'spl_grant',
                    'particulars' => 'SPL for '.($year + 1), 'earned' => 3]);
                $stats['spl']++;
            }
        });
        AuditLog::record('leave.year_posted', null, ['year' => $year] + $stats);

        return $stats;
    }

    private function period(LeaveApplication $l): string
    {
        $from = $l->date_from->format('M j');

        return $l->date_from->eq($l->date_to) ? $from.($l->half_day ? ' ('.strtoupper($l->half_day).')' : '') : $from.'–'.$l->date_to->format('M j');
    }
}
