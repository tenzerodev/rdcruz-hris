<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\LoyaltyAward;
use App\Models\Position;
use App\Models\Saln;
use App\Models\ServiceRecord;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/** Loyalty awards, Report on Appointments Issued, Plantilla of Personnel and SALN filing. */
class CscReportController extends Controller
{
    /**
     * Who reaches a loyalty milestone in the year: 10 years of continuous service, then every 5.
     *
     * @return Collection<int, array{employee: Employee, years: int, date: Carbon, amount: float, award: ?LoyaltyAward}>
     */
    private function loyaltyFor(int $year): Collection
    {
        $cfg = config('hris.loyalty');
        $awards = LoyaltyAward::all()->groupBy('employee_id');

        return Employee::where('is_active', true)->whereNotNull('date_hired')->orderBy('last_name')->get()
            ->filter(fn ($e) => $e->rules()['group'] === 'plantilla')
            ->map(function (Employee $e) use ($year, $cfg, $awards) {
                $years = $year - $e->date_hired->year;
                if ($years < $cfg['first'] || ($years - $cfg['first']) % $cfg['every'] !== 0) {
                    return null;
                }

                return [
                    'employee' => $e, 'years' => $years, 'date' => $e->date_hired->copy()->addYears($years),
                    'amount' => (float) ($years === $cfg['first'] ? $cfg['first_amount'] : $cfg['next_amount']),
                    'award' => ($awards[$e->id] ?? collect())->firstWhere('years', $years),
                ];
            })->filter()->sortBy('date')->values();
    }

    public function loyalty(Request $request)
    {
        $year = $request->integer('year') ?: now()->year;

        return view('admin.reports.loyalty', ['rows' => $this->loyaltyFor($year), 'year' => $year, 'print' => $request->boolean('print'), 'settings' => Setting::allValues()]);
    }

    public function award(Request $request, Employee $employee)
    {
        $data = $request->validate(['years' => ['required', 'integer', 'min:1', 'max:60'], 'amount' => ['required', 'numeric', 'min:0']]);
        $award = LoyaltyAward::updateOrCreate(['employee_id' => $employee->id, 'years' => $data['years']], [
            'milestone_date' => $employee->date_hired?->copy()->addYears($data['years'])->toDateString() ?? today()->toDateString(),
            'amount' => $data['amount'], 'awarded_at' => now(), 'created_by' => $request->user()->id,
        ]);
        AuditLog::record('loyalty.awarded', $award, ['years' => $data['years']]);

        return back()->with('status', "{$employee->full_name}: {$data['years']}-year loyalty award recorded.");
    }

    /** Report on Appointments Issued (RAI) for a month, from the service records. */
    public function rai(Request $request)
    {
        $month = $request->date('month', 'Y-m') ?? now()->startOfMonth();
        $rows = ServiceRecord::with('employee.plantillaPosition')
            ->whereYear('date_from', $month->year)->whereMonth('date_from', $month->month)
            ->where(fn ($q) => $q->whereNull('remarks')->orWhereNotIn('remarks', ['Step increment', 'Salary adjustment']))
            ->orderBy('date_from')->get()
            ->filter(fn ($r) => $r->employee && $r->employee->rules()['group'] === 'plantilla')
            ->map(fn ($r) => [
                'record' => $r, 'employee' => $r->employee,
                'nature' => $r->remarks ?: (ServiceRecord::where('employee_id', $r->employee_id)->where('date_from', '<', $r->date_from)->exists() ? 'Promotion / reappointment' : 'Original'),
            ])->values();

        return view('admin.reports.rai', ['rows' => $rows, 'month' => $month, 'print' => $request->boolean('print'), 'settings' => Setting::allValues()]);
    }

    /** Plantilla of Personnel: every item, its incumbent (or vacant), grade, step and salary. */
    public function plantilla(Request $request)
    {
        $positions = Position::with('employee')->where('is_active', true)->orderBy('office')->orderByDesc('salary_grade')->orderBy('item_no')->get();

        return view('admin.reports.plantilla', ['positions' => $positions, 'print' => $request->boolean('print'), 'settings' => Setting::allValues()]);
    }

    /** SALN filing for a year (as of December 31; due April 30 of the next year). */
    public function saln(Request $request)
    {
        $year = $request->integer('year') ?: now()->year - 1;
        $filed = Saln::with('receiver')->where('year', $year)->get()->keyBy('employee_id');
        $employees = Employee::where('is_active', true)->orderBy('last_name')->orderBy('first_name')->get()
            ->filter(fn ($e) => $e->rules()['group'] === 'plantilla')->values();

        if ($request->input('format') === 'csv') {
            return response()->streamDownload(function () use ($employees, $filed) {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['Employee No', 'Name', 'Position', 'Office', 'Filed on', 'Filing', 'Remarks']);
                foreach ($employees as $e) {
                    $s = $filed[$e->id] ?? null;
                    fputcsv($out, [$e->employee_no, $e->full_name, $e->position, $e->office, $s?->filed_on->toDateString() ?? 'NOT FILED', $s ? Saln::FILING[$s->filing] : '', $s?->remarks]);
                }
                fclose($out);
            }, "saln-{$year}.csv", ['Content-Type' => 'text/csv']);
        }

        return view('admin.reports.saln', ['employees' => $employees, 'filed' => $filed, 'year' => $year]);
    }

    public function recordSaln(Request $request, Employee $employee)
    {
        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'], 'filed_on' => ['required', 'date'],
            'filing' => ['required', 'in:'.implode(',', array_keys(Saln::FILING))], 'remarks' => ['nullable', 'string', 'max:250'],
        ]);
        $saln = Saln::updateOrCreate(['employee_id' => $employee->id, 'year' => $data['year']], [
            'filed_on' => $data['filed_on'], 'filing' => $data['filing'], 'remarks' => $data['remarks'] ?? null, 'received_by' => $request->user()->id,
        ]);
        AuditLog::record('saln.received', $saln, ['year' => $data['year']]);

        return back()->with('status', "SALN {$data['year']} of {$employee->full_name} recorded as received.");
    }
}
