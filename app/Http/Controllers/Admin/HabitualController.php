<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Services\HabitualReport;
use Illuminate\Http\Request;

/** CSC habitual tardiness and absenteeism report, by month, from the DTR. */
class HabitualController extends Controller
{
    public function index(Request $request, HabitualReport $report)
    {
        $request->validate([
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'office' => ['nullable', 'string', 'max:150'],
            'show' => ['nullable', 'in:flagged,all'],
            'format' => ['nullable', 'in:html,csv'],
        ]);
        @set_time_limit(300);
        $year = $request->integer('year') ?: now()->year;
        $show = $request->input('show', 'flagged');

        $employees = Employee::where('is_active', true)
            ->when($request->input('office'), fn ($q, $o) => $q->where('office', $o))
            ->orderBy('last_name')->orderBy('first_name')->get();
        $rows = $report->forYear($year, $employees);
        $flagged = $rows->filter(fn ($r) => $r['tardy'] || $r['absent']);
        $shown = $show === 'all' ? $rows : $flagged;

        if ($request->input('format') === 'csv') {
            return response()->streamDownload(function () use ($shown) {
                $out = fopen('php://output', 'w');
                $months = array_map(fn ($m) => date('M', mktime(0, 0, 0, $m, 1)), range(1, 12));
                fputcsv($out, ['Employee No', 'Name', 'Office', ...array_map(fn ($m) => "$m tardy", $months), ...array_map(fn ($m) => "$m absent (days)", $months), 'Habitually tardy', 'Habitual absenteeism']);
                foreach ($shown as $r) {
                    $t = $a = [];
                    for ($m = 1; $m <= 12; $m++) {
                        $t[] = $r['months'][$m]['tardy'] ?? '';
                        $a[] = $r['months'][$m]['absent'] ?? '';
                    }
                    fputcsv($out, [$r['employee']->employee_no, $r['employee']->full_name, $r['employee']->office, ...$t, ...$a, $r['tardy'] ?? '', $r['absent'] ?? '']);
                }
                fclose($out);
            }, "habitual-tardiness-absences-{$year}.csv", ['Content-Type' => 'text/csv']);
        }

        return view('admin.reports.habitual', [
            'year' => $year,
            'rows' => $shown,
            'total' => $rows->count(),
            'tardyCount' => $rows->filter(fn ($r) => $r['tardy'])->count(),
            'absentCount' => $rows->filter(fn ($r) => $r['absent'])->count(),
            'show' => $show,
            'offices' => Employee::where('is_active', true)->whereNotNull('office')->distinct()->orderBy('office')->pluck('office'),
            'rules' => config('hris.habitual'),
        ]);
    }
}
