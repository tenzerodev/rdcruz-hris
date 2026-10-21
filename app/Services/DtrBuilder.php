<?php

namespace App\Services;

use App\Models\CaseSuspension;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveApplication;
use App\Models\OvertimeAuthorization;
use App\Models\PassSlip;
use App\Models\Punch;
use App\Models\Setting;
use App\Models\TravelOrder;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Builds one employee's month: the CSC Form No. 48 rows, undertime, absences, leave and travel days,
 * and overtime (with approval, the 2-hour minimum and the weekend full-week rule).
 */
class DtrBuilder
{
    private array $holidays = [];

    private Collection $punches;

    private Collection $leaves;

    private Collection $travel;

    private Collection $passSlips;

    private Collection $suspensions;

    public function build(Employee $employee, int $year, int $month): array
    {
        $start = CarbonImmutable::create($year, $month, 1)->startOfDay();
        $end = $start->endOfMonth()->startOfDay();
        // Load from the Monday before the month so the weekend rule can look back at the whole week.
        $ctxStart = $start->startOfWeek(CarbonInterface::MONDAY);
        $this->load($employee, $ctxStart, $end);

        $s = $this->settings();
        $ot = $this->overtimeRules();
        $hours = $employee->officialHours();
        $flexi = $employee->schedule_type === 'flexi';
        $authorizations = OvertimeAuthorization::approved()->where('employee_id', $employee->id)
            ->whereDate('date_from', '<=', $end->toDateString())->whereDate('date_to', '>=', $start->toDateString())->get();

        $rows = [];
        $totals = ['undertime' => 0, 'present' => 0, 'absent' => 0, 'leave_days' => 0.0, 'travel_days' => 0, 'incomplete' => 0, 'tardy' => 0, 'suspended' => 0, 'suspended_unpaid' => 0];
        $otTotals = ['actual' => 0, 'credited' => 0, 'coc' => 0, 'unapproved' => 0];

        for ($date = $start; $date <= $end; $date = $date->addDay()) {
            $key = $date->toDateString();
            $times = $this->times($key);
            $hasPunch = (bool) array_filter($times);
            $isWorkday = $this->isWorkday($date);
            $leave = $this->leaveOn($key);
            $travel = $this->travelOn($key);
            $suspension = $this->suspensions->first(fn (CaseSuspension $x) => $x->covers($key));

            // Partial-day OB counts as present from/to its hours.
            if ($travel && ! $travel->isWholeDay()) {
                $times = $this->applyWindow($times, $key, $travel->time_from, $travel->time_to);
                $hasPunch = true;
            }

            // Pass slips: leaving for the day ends the day at the time out (personal) or the official end (official).
            $slips = $this->passSlips[$key] ?? collect();
            $personalMinutes = 0;
            if ($hasPunch) {
                foreach ($slips as $slip) {
                    if ($slip->returns()) {
                        $personalMinutes += $slip->type === 'personal' ? $this->minutesOutsideLunch($slip, $hours) : 0;
                    } elseif ($slip->type === 'official') {
                        $dayEnd = $this->officialEnd($times, $hours, $flexi, $s);
                        if ($dayEnd && $slip->outAt() < $dayEnd) {
                            $times = $this->applyWindow($times, $key, $slip->outAt(), $dayEnd);
                        }
                    } else {
                        $departKey = $times['pm_out'] || ! $times['am_out'] ? 'pm_out' : 'am_out';
                        $out = CarbonImmutable::parse("$key {$slip->outAt()}");
                        if (! $times[$departKey] || $times[$departKey]->gt($out)) {
                            $times[$departKey] = $out;
                        }
                    }
                }
            }

            $note = null;
            if (! $hasPunch) {
                $note = match (true) {
                    isset($this->holidays[$key]) => $this->holidays[$key]->name,
                    $suspension && $isWorkday => 'SUSPENDED',
                    $leave && $isWorkday => mb_strtoupper($leave->type_name),
                    $travel !== null => mb_strtoupper(TravelOrder::TYPES[$travel->type]),
                    $date->isSunday() => 'SUNDAY',
                    $date->isSaturday() && ! $isWorkday => 'SATURDAY',
                    default => null,
                };
            }

            // Undertime and attendance
            $undertime = 0;
            $incomplete = false;
            $absent = false;
            $tardy = false;
            if ($isWorkday) {
                if ($suspension && ! $hasPunch) {
                    $totals['suspended']++;
                    $totals['suspended_unpaid'] += $suspension->with_pay ? 0 : 1;
                } elseif ($leave && ! $leave->half_day) {
                    $totals['leave_days'] += 1;
                } elseif ($travel && $travel->isWholeDay()) {
                    $totals['travel_days']++;
                } elseif ($hasPunch) {
                    $totals['present']++;
                    if ($leave && $leave->half_day) {
                        $totals['leave_days'] += 0.5;
                        $undertime = max(0, intdiv($s['required'], 2) - $this->worked($times, $hours, $s));
                    } elseif ($flexi) {
                        [$undertime, $incomplete] = $this->flexiUndertime($times, $s);
                        $tardy = $this->isTardy($times, $hours, $s, true);
                    } else {
                        $undertime = $this->undertime($times, $hours, $s['grace'], $s['half_day_ut']);
                        $tardy = $this->isTardy($times, $hours, $s, false);
                    }
                    $undertime += $personalMinutes;
                } elseif ($date->lte(today()) && $this->employedOn($employee, $date)) {
                    $absent = true;
                    $totals['absent']++;
                }
            }
            $totals['undertime'] += $undertime;
            $totals['incomplete'] += $incomplete ? 1 : 0;
            $totals['tardy'] += $tardy ? 1 : 0;

            // Overtime
            $otActual = $hasPunch && ! ($leave && ! $leave->half_day) ? $this->overtime($times, $hours, $isWorkday, $ot, $flexi ? $s : null) : 0;
            [$otCredited, $reason, $auth] = $this->creditOvertime($employee, $date, $otActual, $isWorkday, $ot, $authorizations);
            $coc = $ot['coc'] && $otCredited ? (int) round($otCredited * ($isWorkday ? $ot['weekday_rate'] : $ot['restday_rate'])) : 0;
            $otTotals['actual'] += $otActual;
            $otTotals['credited'] += $otCredited;
            $otTotals['coc'] += $coc;
            $otTotals['unapproved'] += $otActual - $otCredited;

            $rows[] = [
                'day' => $date->day,
                'date' => $date,
                'am_in' => $times['am_in']?->format('g:i'),
                'am_out' => $times['am_out']?->format('g:i'),
                'pm_in' => $times['pm_in']?->format('g:i'),
                'pm_out' => $times['pm_out']?->format('g:i'),
                'note' => $note,
                'is_workday' => $isWorkday,
                'absent' => $absent,
                'tardy' => $tardy,
                'incomplete' => $incomplete,
                'leave' => $leave,
                'travel' => $travel,
                'pass_slips' => $slips,
                'ut_hours' => $undertime ? intdiv($undertime, 60) : null,
                'ut_minutes' => $undertime ? $undertime % 60 : null,
                'undertime' => $undertime,
                'ot_actual' => $otActual,
                'ot_credited' => $otCredited,
                'ot_coc' => $coc,
                'ot_reason' => $reason,
                'ot_authorization' => $auth,
            ];
        }

        $cocCap = $ot['coc_cap'] * 60;
        $otTotals['coc_capped'] = $cocCap > 0 ? min($otTotals['coc'], $cocCap) : $otTotals['coc'];

        // Pad to 31 rows so the printed form always has the same shape.
        for ($d = count($rows) + 1; $d <= 31; $d++) {
            $rows[] = ['day' => $d, 'date' => null, 'am_in' => null, 'am_out' => null, 'pm_in' => null, 'pm_out' => null,
                'note' => null, 'is_workday' => false, 'absent' => false, 'tardy' => false, 'incomplete' => false, 'leave' => null, 'travel' => null, 'pass_slips' => collect(),
                'ut_hours' => null, 'ut_minutes' => null, 'undertime' => 0,
                'ot_actual' => 0, 'ot_credited' => 0, 'ot_coc' => 0, 'ot_reason' => null, 'ot_authorization' => null];
        }

        $fmt = fn (string $hhmm) => CarbonImmutable::createFromFormat('H:i', $hhmm)->format('g:i');

        return [
            'employee' => $employee,
            'month' => $start,
            'hours' => $hours,
            'hours_label' => $flexi
                ? 'Flexi: in '.$fmt($s['flexi_start']).'–'.$fmt($s['flexi_end']).', '.($s['required'] / 60).' hrs'
                : $fmt($hours['am_in']).'–'.$fmt($hours['am_out']).', '.$fmt($hours['pm_in']).'–'.$fmt($hours['pm_out']),
            'rows' => $rows,
            'total_minutes' => $totals['undertime'],
            'total_hours' => intdiv($totals['undertime'], 60),
            'total_rem_minutes' => $totals['undertime'] % 60,
            'days_present' => $totals['present'],
            'days_absent' => $totals['absent'],
            'leave_days' => $totals['leave_days'],
            'travel_days' => $totals['travel_days'],
            'incomplete_days' => $totals['incomplete'],
            'tardy_days' => $totals['tardy'],
            'suspended_days' => $totals['suspended'],
            'suspended_unpaid_days' => $totals['suspended_unpaid'],
            'overtime' => $otTotals,
            'overtime_rules' => $ot,
        ];
    }

    public function settings(): array
    {
        return [
            'flexi_start' => Setting::get('flexi_start', '07:00'),
            'flexi_end' => Setting::get('flexi_end', '10:00'),
            'required' => (int) Setting::get('required_minutes', '480'),
            'lunch' => (int) Setting::get('lunch_minutes', '60'),
            'grace' => (int) Setting::get('grace_minutes', '0'),
            'half_day_ut' => Setting::get('half_day_absence_as_undertime') === '1',
        ];
    }

    public function overtimeRules(): array
    {
        return [
            'requires_approval' => Setting::get('ot_requires_approval') === '1',
            'min' => (int) Setting::get('ot_min_minutes', '120'),
            'rounding' => max(1, (int) Setting::get('ot_rounding', '1')),
            'early' => Setting::get('ot_count_early_arrival') === '1',
            'full_week' => Setting::get('ot_weekend_full_week') === '1',
            'full_week_holidays' => Setting::get('ot_full_week_holidays') === '1',
            'coc' => Setting::get('ot_coc_enabled') === '1',
            'weekday_rate' => (float) Setting::get('ot_coc_weekday_rate', '1'),
            'restday_rate' => (float) Setting::get('ot_coc_restday_rate', '1.5'),
            'coc_cap' => (float) Setting::get('ot_coc_monthly_cap', '40'),
        ];
    }

    public function isWorkday(CarbonInterface $date): bool
    {
        if (isset($this->holidays[$date->toDateString()]) || $date->isSunday()) {
            return false;
        }

        return ! $date->isSaturday() || Setting::get('work_saturday') === '1';
    }

    /**
     * Flexi-time undertime: arrival is counted from flexi_start at the earliest; arriving after flexi_end
     * is late; required departure = counted arrival + required minutes + lunch. A lunch break longer than
     * allowed also counts. Returns [minutes, incomplete].
     */
    public function flexiUndertime(array $t, array $s): array
    {
        $m = fn (string $hhmm) => ((int) substr($hhmm, 0, 2)) * 60 + (int) substr($hhmm, 3, 2);
        $at = fn ($time) => $time ? $time->hour * 60 + $time->minute : null;
        $arrival = $at($t['am_in'] ?? null) ?? $at($t['pm_in'] ?? null);
        $departure = $at($t['pm_out'] ?? null) ?? $at($t['am_out'] ?? null);

        if ($arrival === null || $departure === null || $departure <= $arrival) {
            return [0, true];
        }

        $start = min(max($arrival, $m($s['flexi_start'])), $m($s['flexi_end']));
        $requiredOut = $start + $s['required'] + $s['lunch'];
        $late = $arrival - $m($s['flexi_end']);
        $late = $late > $s['grace'] ? $late : 0;
        $early = max(0, $requiredOut - $departure);
        $longLunch = 0;
        if (isset($t['am_out'], $t['pm_in']) && $t['am_in'] && $t['pm_out']) {
            $longLunch = max(0, ($at($t['pm_in']) - $at($t['am_out'])) - $s['lunch']);
        }

        return [$late + $early + $longLunch, false];
    }

    /** First day attendance was recorded in the HRIS (setting, or the earliest punch). */
    public static function trackingStart(): ?CarbonImmutable
    {
        static $cache = [];
        $set = Setting::get('attendance_start_date');
        if ($set) {
            return CarbonImmutable::parse($set)->startOfDay();
        }
        $key = 'first';
        if (! array_key_exists($key, $cache) || app()->runningUnitTests()) {
            $first = Punch::min('work_date');
            $cache[$key] = $first ? CarbonImmutable::parse($first)->startOfDay() : null;
        }

        return $cache[$key];
    }

    /** No absences before attendance tracking began, before the employee started, or after they left. */
    private function employedOn(Employee $employee, CarbonInterface $date): bool
    {
        $tracking = self::trackingStart();
        if (! $tracking || $date->lt($tracking)) {
            return false;
        }
        $start = $employee->date_hired ?? $employee->contract_start;
        if ($start && $date->lt($start->copy()->startOfDay())) {
            return false;
        }

        return ! ($employee->date_separated && $date->gt($employee->date_separated->copy()->endOfDay()));
    }

    /**
     * Late arrival, for CSC tardiness counts (regardless of minutes). Flexi: arriving after the latest
     * flexi time-in. Fixed: arriving after the official AM time-in, or back late from lunch.
     */
    public function isTardy(array $t, array $h, array $s, bool $flexi): bool
    {
        $m = fn (string $hhmm) => ((int) substr($hhmm, 0, 2)) * 60 + (int) substr($hhmm, 3, 2);
        $at = fn ($time) => $time ? $time->hour * 60 + $time->minute : null;

        if ($flexi) {
            $arrival = $at($t['am_in'] ?? null) ?? $at($t['pm_in'] ?? null);

            return $arrival !== null && $arrival - $m($s['flexi_end']) > $s['grace'];
        }

        $amIn = $at($t['am_in'] ?? null);
        $pmIn = $at($t['pm_in'] ?? null);
        $lateAm = $amIn !== null && $amIn - $m($h['am_in']) > $s['grace'];
        $lateLunch = $amIn !== null && $pmIn !== null && $pmIn - $m($h['pm_in']) > $s['grace'];

        return $lateAm || $lateLunch;
    }

    /** Fixed-schedule undertime: late arrivals + early departures, optional half-day absence. */
    public function undertime(array $t, array $h, int $grace = 0, bool $halfDayAsUndertime = true): int
    {
        $m = fn (?string $hhmm) => $hhmm ? ((int) substr($hhmm, 0, 2)) * 60 + (int) substr($hhmm, 3, 2) : null;
        $at = fn ($time) => $time ? $time->hour * 60 + $time->minute : null;

        [$offAmIn, $offAmOut, $offPmIn, $offPmOut] = [$m($h['am_in']), $m($h['am_out']), $m($h['pm_in']), $m($h['pm_out'])];
        [$amIn, $amOut, $pmIn, $pmOut] = [$at($t['am_in']), $at($t['am_out']), $at($t['pm_in']), $at($t['pm_out'])];

        $late = fn (?int $actual, int $official) => $actual !== null && $actual - $official > $grace ? $actual - $official : 0;
        $early = fn (?int $actual, int $official) => $actual !== null && $official > $actual ? $official - $actual : 0;

        $total = $late($amIn, $offAmIn) + $early($amOut, $offAmOut) + $late($pmIn, $offPmIn) + $early($pmOut, $offPmOut);

        if ($halfDayAsUndertime) {
            $amMissing = $amIn === null && $amOut === null;
            $pmMissing = $pmIn === null && $pmOut === null;
            if ($amMissing && ! $pmMissing) {
                $total += $offAmOut - $offAmIn;
            }
            if ($pmMissing && ! $amMissing) {
                $total += $offPmOut - $offPmIn;
            }
        }

        return max(0, $total);
    }

    /**
     * Overtime minutes rendered in a day, before approval.
     * Work day: after required departure (flexi) or official PM departure (fixed); early arrival optional.
     * Rest day / holiday: all time worked, less lunch when only arrival and departure were punched.
     */
    public function overtime(array $t, array $h, bool $isWorkday, array $rules, ?array $flexi = null): int
    {
        $m = fn (?string $hhmm) => $hhmm ? ((int) substr($hhmm, 0, 2)) * 60 + (int) substr($hhmm, 3, 2) : null;
        $at = fn ($time) => $time ? $time->hour * 60 + $time->minute : null;
        [$amIn, $amOut, $pmIn, $pmOut] = [$at($t['am_in']), $at($t['am_out']), $at($t['pm_in']), $at($t['pm_out'])];

        if ($isWorkday) {
            if ($flexi) {
                $arrival = $amIn ?? $pmIn;
                if ($arrival === null || $pmOut === null) {
                    return 0;
                }
                $start = min(max($arrival, $m($flexi['flexi_start'])), $m($flexi['flexi_end']));
                $minutes = max(0, $pmOut - ($start + $flexi['required'] + $flexi['lunch']));
            } else {
                $minutes = $pmOut !== null ? max(0, $pmOut - $m($h['pm_out'])) : 0;
                if ($rules['early'] && $amIn !== null) {
                    $minutes += max(0, $m($h['am_in']) - $amIn);
                }
            }
        } else {
            $minutes = $this->worked($t, $h, ['lunch' => null]);
        }

        if ($minutes < $rules['min']) {
            return 0;
        }

        return intdiv($minutes, $rules['rounding']) * $rules['rounding'];
    }

    /** Minutes actually worked: complete AM and PM pairs, or arrival→departure less the official lunch break. */
    public function worked(array $t, array $h, array $s): int
    {
        $m = fn (?string $hhmm) => $hhmm ? ((int) substr($hhmm, 0, 2)) * 60 + (int) substr($hhmm, 3, 2) : null;
        $at = fn ($time) => $time ? $time->hour * 60 + $time->minute : null;
        [$amIn, $amOut, $pmIn, $pmOut] = [$at($t['am_in']), $at($t['am_out']), $at($t['pm_in']), $at($t['pm_out'])];

        $minutes = 0;
        if ($amIn !== null && $amOut !== null) {
            $minutes += max(0, $amOut - $amIn);
        }
        if ($pmIn !== null && $pmOut !== null) {
            $minutes += max(0, $pmOut - $pmIn);
        }
        if ($amIn !== null && $pmOut !== null && ($amOut === null || $pmIn === null)) {
            $lunch = max(0, min($pmOut, $m($h['pm_in'])) - max($amIn, $m($h['am_out'])));
            $minutes = max(0, $pmOut - $amIn - $lunch);
        }

        return $minutes;
    }

    /**
     * Applies eligibility, the weekend full-week rule, approval and the daily cap.
     *
     * @return array{0: int, 1: ?string, 2: ?OvertimeAuthorization}
     */
    private function creditOvertime(Employee $employee, CarbonImmutable $date, int $actual, bool $isWorkday, array $ot, Collection $authorizations): array
    {
        if (! $actual) {
            return [0, null, null];
        }
        $auth = $authorizations->first(fn ($a) => $date->betweenIncluded($a->date_from, $a->date_to));

        if (! $employee->overtimeEligible()) {
            return [0, 'Not eligible ('.$employee->type_label.')', $auth];
        }

        $isWeekend = $date->isSaturday() || $date->isSunday();
        $isHoliday = isset($this->holidays[$date->toDateString()]);
        if ($ot['full_week'] && ! $isWorkday && ($isWeekend || ($isHoliday && $ot['full_week_holidays']))) {
            if ($blocker = $this->weekBlocker($date)) {
                return [0, $blocker, $auth];
            }
        }

        if ($ot['requires_approval'] && ! $auth) {
            return [0, 'No approved overtime request', null];
        }

        $credited = $auth?->max_minutes_per_day ? min($actual, $auth->max_minutes_per_day) : $actual;

        return [$credited, $credited < $actual ? 'Over the approved hours' : null, $auth];
    }

    /** First leave or absence on a work day Mon–Fri of the same week before $date, as a reason, or null. */
    public function weekBlocker(CarbonImmutable $date): ?string
    {
        $monday = $date->startOfWeek(CarbonInterface::MONDAY);
        for ($d = $monday; $d->lt($date) && $d->dayOfWeekIso <= 5; $d = $d->addDay()) {
            if (! $this->isWorkday($d) || $d->gt(today())) {
                continue;
            }
            $key = $d->toDateString();
            if ($leave = $this->leaveOn($key)) {
                return 'On leave ('.$leave->type.') '.$d->format('M j').' — weekend OT not allowed';
            }
            if (! $this->travelOn($key) && ! array_filter($this->times($key))) {
                return 'Absent '.$d->format('M j').' — weekend OT not allowed';
            }
        }

        return null;
    }

    private function load(Employee $employee, CarbonImmutable $from, CarbonImmutable $to): void
    {
        [$f, $t] = [$from->toDateString(), $to->toDateString()];

        $this->punches = Punch::where('employee_id', $employee->id)
            ->whereDate('work_date', '>=', $f)->whereDate('work_date', '<=', $t)->get()
            ->groupBy(fn (Punch $p) => $p->work_date->toDateString());

        $this->holidays = Holiday::whereDate('date', '>=', $f)->whereDate('date', '<=', $t)->get()
            ->keyBy(fn (Holiday $h) => $h->date->toDateString())->all();

        $this->leaves = LeaveApplication::where('employee_id', $employee->id)->where('status', 'approved')
            ->whereDate('date_from', '<=', $t)->whereDate('date_to', '>=', $f)->get();

        $this->travel = $employee->travelOrders()->where('status', 'approved')
            ->whereDate('date_from', '<=', $t)->whereDate('date_to', '>=', $f)->get();

        $this->suspensions = CaseSuspension::where('employee_id', $employee->id)
            ->whereDate('date_from', '<=', $t)->whereDate('date_to', '>=', $f)->get();

        $this->passSlips = PassSlip::where('employee_id', $employee->id)->where('status', 'approved')
            ->whereDate('date', '>=', $f)->whereDate('date', '<=', $t)->get()
            ->groupBy(fn (PassSlip $p) => $p->date->toDateString());
    }

    private function times(string $key): array
    {
        $day = ($this->punches[$key] ?? collect())->keyBy('type');
        $times = [];
        foreach (Punch::TYPES as $type) {
            $times[$type] = isset($day[$type]) ? $day[$type]->punched_at : null;
        }

        return $times;
    }

    private function leaveOn(string $key): ?LeaveApplication
    {
        return $this->leaves->first(fn (LeaveApplication $l) => $l->covers($key));
    }

    private function travelOn(string $key): ?TravelOrder
    {
        return $this->travel->first(fn (TravelOrder $o) => $key >= $o->date_from->toDateString() && $key <= $o->date_to->toDateString());
    }

    /** End of the required day (H:i): official PM out, or for flexi, counted arrival + required hours + lunch. */
    private function officialEnd(array $times, array $hours, bool $flexi, array $s): ?string
    {
        if (! $flexi) {
            return $hours['pm_out'];
        }
        $arrival = $times['am_in'] ?? $times['pm_in'];
        if (! $arrival) {
            return null;
        }
        $m = fn (string $hhmm) => ((int) substr($hhmm, 0, 2)) * 60 + (int) substr($hhmm, 3, 2);
        $start = min(max($arrival->hour * 60 + $arrival->minute, $m($s['flexi_start'])), $m($s['flexi_end']));
        $end = min(23 * 60 + 59, $start + $s['required'] + $s['lunch']);

        return sprintf('%02d:%02d', intdiv($end, 60), $end % 60);
    }

    /** A personal pass slip's minutes out, not counting the lunch break. */
    private function minutesOutsideLunch(PassSlip $slip, array $hours): int
    {
        $m = fn (string $hhmm) => ((int) substr($hhmm, 0, 2)) * 60 + (int) substr($hhmm, 3, 2);
        [$out, $back] = [$m($slip->outAt()), $m($slip->backAt())];
        $lunch = max(0, min($back, $m($hours['pm_in'])) - max($out, $m($hours['am_out'])));

        return max(0, $back - $out - $lunch);
    }

    /** Treat a window (partial-day OB, official pass slip) as time present: earliest arrival, latest departure. */
    private function applyWindow(array $times, string $key, string $fromTime, string $toTime): array
    {
        $from = CarbonImmutable::parse("$key {$fromTime}");
        $to = CarbonImmutable::parse("$key {$toTime}");
        $arrivalKey = $times['am_in'] || ! $times['pm_in'] ? 'am_in' : 'pm_in';
        $departKey = $times['pm_out'] || ! $times['am_out'] ? 'pm_out' : 'am_out';
        if (! $times[$arrivalKey] || $from->lt($times[$arrivalKey])) {
            $times[$arrivalKey] = $from;
        }
        if (! $times[$departKey] || $to->gt($times[$departKey])) {
            $times[$departKey] = $to;
        }

        return $times;
    }
}
