<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Ipcr;
use App\Models\LeaveApplication;
use App\Models\PerformancePeriod;
use App\Models\Position;
use App\Models\Training;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/** Figures for the HR analytics page: headcount, vacancies, turnover, leave, tardiness, demographics. */
class Analytics
{
    public function __construct(private HabitualReport $habitual) {}

    public function build(int $year): array
    {
        $active = Employee::where('is_active', true)->get();
        $all = Employee::all();
        $positions = Position::with(['employee' => fn ($q) => $q->where('is_active', true)])->where('is_active', true)->get();
        $vacant = $positions->filter(fn ($p) => ! $p->employee)->count();
        $months = range(1, 12);
        $label = fn (int $m) => date('M', mktime(0, 0, 0, $m, 1));

        $hires = $all->filter(fn ($e) => $e->date_hired?->year === $year)->countBy(fn ($e) => $e->date_hired->month);
        $exits = $all->filter(fn ($e) => $e->date_separated?->year === $year)->countBy(fn ($e) => $e->date_separated->month);
        $startCount = $all->filter(fn ($e) => (! $e->date_hired || $e->date_hired->year < $year) && (! $e->date_separated || $e->date_separated->year >= $year))->count();
        $endCount = $all->filter(fn ($e) => (! $e->date_hired || $e->date_hired->year <= $year) && (! $e->date_separated || $e->date_separated->year > $year))->count();
        $avgHeadcount = max(1, ($startCount + $endCount) / 2);

        $leave = LeaveApplication::where('status', 'approved')->whereYear('date_from', $year)->get();
        $leaveByMonth = $leave->groupBy(fn ($l) => $l->date_from->month)->map(fn ($g) => round($g->sum('days'), 1));
        $leaveByType = $leave->groupBy('type')->map(fn ($g) => round($g->sum('days'), 1))->sortDesc()
            ->mapWithKeys(fn ($v, $k) => [config("hris.leave_types.$k.name", $k) => $v]);

        $tardy = $this->tardiness($year, $active);

        $period = PerformancePeriod::whereHas('ipcrs', fn ($q) => $q->whereNotNull('adjectival'))->orderByDesc('ends_on')->first();
        $ratings = $period ? Ipcr::where('performance_period_id', $period->id)->whereNotNull('adjectival')->get()->countBy('adjectival') : collect();

        $ages = $active->filter(fn ($e) => $e->birth_date)->map(fn ($e) => $e->birth_date->age);
        $bands = ['Under 30' => [0, 29], '30–39' => [30, 39], '40–49' => [40, 49], '50–59' => [50, 59], '60 and over' => [60, 200]];

        return [
            'headcount' => $active->count(),
            'by_group' => $active->countBy(fn ($e) => config("hris.groups.{$e->group}", $e->group))->sortDesc()->all(),
            'by_office' => $active->countBy(fn ($e) => $e->office ?: '—')->sortDesc()->take(12)->all(),
            'plantilla' => ['items' => $positions->count(), 'filled' => $positions->count() - $vacant, 'vacant' => $vacant,
                'rate' => $positions->count() ? round($vacant / $positions->count() * 100, 1) : 0],
            'movement' => ['labels' => array_map($label, $months), 'series' => [
                'Hired' => array_map(fn ($m) => (int) ($hires[$m] ?? 0), $months),
                'Separated' => array_map(fn ($m) => (int) ($exits[$m] ?? 0), $months),
            ]],
            'turnover' => round($exits->sum() / $avgHeadcount * 100, 1),
            'hired' => $hires->sum(),
            'separated' => $exits->sum(),
            'separation_reasons' => $all->filter(fn ($e) => $e->date_separated?->year === $year)->countBy(fn ($e) => $e->separation_reason ?: 'Not stated')->sortDesc()->all(),
            'leave' => ['labels' => array_map($label, $months), 'series' => ['Days of leave' => array_map(fn ($m) => (float) ($leaveByMonth[$m] ?? 0), $months)]],
            'leave_by_type' => $leaveByType->take(8)->all(),
            'leave_total' => round($leave->sum('days'), 1),
            'tardiness' => ['labels' => array_map($label, $months), 'series' => ['Late arrivals' => array_map(fn ($m) => $tardy['tardy'][$m] ?? 0, $months),
                'Absences' => array_map(fn ($m) => $tardy['absent'][$m] ?? 0, $months)]],
            'ratings' => ['period' => $period?->name, 'counts' => collect(config('hris.ipcr.scale'))->mapWithKeys(fn ($s) => [$s[1] => (int) ($ratings[$s[1]] ?? 0)])->all()],
            'sex' => ['Female' => $active->where('sex', 'female')->count(), 'Male' => $active->where('sex', 'male')->count(), 'Not recorded' => $active->whereNull('sex')->count()],
            'ages' => collect($bands)->map(fn ($b) => $ages->filter(fn ($a) => $a >= $b[0] && $a <= $b[1])->count())->all(),
            'retiring' => $active->filter(fn ($e) => $e->birth_date && $e->birth_date->copy()->addYears(65)->year <= $year + 5)->sortBy('birth_date')->values(),
            'training_hours' => round((float) Training::whereYear('date_from', $year)->sum('hours') / max(1, $active->count()), 1),
        ];
    }

    /**
     * Late arrivals and absences per month across all active employees (from the DTR). Past months are
     * cached for a day since rebuilding every DTR is slow.
     *
     * @return array{tardy: array<int, int>, absent: array<int, float>}
     */
    private function tardiness(int $year, Collection $active): array
    {
        $compute = function () use ($year, $active) {
            $out = ['tardy' => [], 'absent' => []];
            foreach ($this->habitual->forYear($year, $active) as $row) {
                foreach ($row['months'] as $m => $v) {
                    $out['tardy'][$m] = ($out['tardy'][$m] ?? 0) + $v['tardy'];
                    $out['absent'][$m] = round(($out['absent'][$m] ?? 0) + $v['absent'], 1);
                }
            }

            return $out;
        };

        if (app()->runningUnitTests()) {
            return $compute();
        }

        return Cache::remember("hris.analytics.tardy.$year.".CarbonImmutable::today()->toDateString(), now()->addHours(6), $compute);
    }
}
