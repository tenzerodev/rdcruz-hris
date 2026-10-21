<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\SalaryNotice;
use App\Models\SalaryRate;
use App\Models\SalarySchedule;
use App\Models\Setting;
use App\Services\CompensationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Salary schedules, step increments (NOSI) and salary adjustments (NOSA). */
class CompensationController extends Controller
{
    public function __construct(private CompensationService $comp) {}

    public function index(Request $request)
    {
        $days = (int) $request->integer('days', 60);

        return view('admin.compensation.index', [
            'due' => $this->comp->stepIncrements($days),
            'days' => $days,
            'schedules' => SalarySchedule::withCount('rates')->orderByDesc('effective_date')->get(),
            'current' => SalarySchedule::inEffect(),
            'notices' => SalaryNotice::with('employee')->latest('id')->limit(30)->get(),
            'missingDates' => $this->comp->covered()->filter(fn ($e) => ! $e->step_date && ! $e->date_hired)->count(),
        ]);
    }

    public function grant(Request $request, Employee $employee)
    {
        $data = $request->validate(['effective_date' => ['required', 'date']]);
        $notice = $this->comp->grantStep($employee, Carbon::parse($data['effective_date']), $request->user());

        return back()->with('status', "Step increment granted to {$employee->full_name}: step {$notice->new_step}, ₱".number_format($notice->new_salary, 2).'.')
            ->with('print', route('admin.compensation.notice', $notice));
    }

    public function storeSchedule(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'effective_date' => ['required', 'date'],
            'basis' => ['nullable', 'string', 'max:250'],
            'file' => ['nullable', 'file', 'mimes:csv,txt', 'max:512'],
            'copy_from' => ['nullable', 'exists:salary_schedules,id'],
        ]);
        $schedule = DB::transaction(function () use ($data, $request) {
            $schedule = SalarySchedule::create($data);
            if ($request->hasFile('file')) {
                $this->saveRates($schedule, $this->parseCsv((string) file_get_contents($request->file('file')->getRealPath())));
            } elseif (! empty($data['copy_from'])) {
                $this->saveRates($schedule, SalaryRate::where('salary_schedule_id', $data['copy_from'])->get()
                    ->map(fn ($r) => ['grade' => $r->grade, 'step' => $r->step, 'amount' => $r->amount])->all());
            }

            return $schedule;
        });
        AuditLog::record('compensation.schedule_created', $schedule);

        return redirect()->route('admin.compensation.schedule', $schedule)->with('status', 'Salary schedule saved. Check the amounts below.');
    }

    public function schedule(SalarySchedule $schedule)
    {
        return view('admin.compensation.schedule', [
            'schedule' => $schedule,
            'grid' => $schedule->rates()->get()->mapWithKeys(fn ($r) => [$r->grade.'-'.$r->step => $r->amount])->all(),
            'adjustments' => $this->comp->adjustments($schedule),
            'applied' => SalaryNotice::where('salary_schedule_id', $schedule->id)->where('type', 'nosa')->count(),
        ]);
    }

    public function updateSchedule(Request $request, SalarySchedule $schedule)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'], 'effective_date' => ['required', 'date'], 'basis' => ['nullable', 'string', 'max:250'],
            'rates' => ['array'], 'rates.*.*' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
        ]);
        DB::transaction(function () use ($schedule, $data) {
            $schedule->update(collect($data)->only(['name', 'effective_date', 'basis'])->all());
            $rows = [];
            foreach ($data['rates'] ?? [] as $grade => $steps) {
                foreach ($steps as $step => $amount) {
                    if ($amount !== null && $amount !== '') {
                        $rows[] = ['grade' => (int) $grade, 'step' => (int) $step, 'amount' => (float) $amount];
                    }
                }
            }
            $schedule->rates()->delete();
            $this->saveRates($schedule, $rows);
        });
        AuditLog::record('compensation.schedule_updated', $schedule);

        return back()->with('status', 'Salary schedule saved.');
    }

    public function destroySchedule(SalarySchedule $schedule)
    {
        abort_if(SalaryNotice::where('salary_schedule_id', $schedule->id)->exists(), 422, 'Notices were issued under this schedule; it cannot be deleted.');
        $schedule->delete();

        return redirect()->route('admin.compensation.index')->with('status', 'Salary schedule deleted.');
    }

    public function apply(Request $request, SalarySchedule $schedule)
    {
        $count = $this->comp->applySchedule($schedule, $request->user());

        return back()->with('status', "Salary adjustment applied: {$count} notice(s) issued, salaries and service records updated.")
            ->with('print', route('admin.compensation.notices.print', ['schedule' => $schedule->id]));
    }

    public function template()
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['grade', 'step1', 'step2', 'step3', 'step4', 'step5', 'step6', 'step7', 'step8']);
            fputcsv($out, [1, '', '', '', '', '', '', '', '']);
            fclose($out);
        }, 'salary-schedule-template.csv', ['Content-Type' => 'text/csv']);
    }

    public function notice(SalaryNotice $notice)
    {
        return view('print.salary-notice', ['notices' => collect([$notice->load('employee')]), 'settings' => Setting::allValues()]);
    }

    public function printNotices(Request $request)
    {
        $notices = SalaryNotice::with('employee')
            ->when($request->integer('schedule'), fn ($q, $id) => $q->where('salary_schedule_id', $id)->where('type', 'nosa'))
            ->when($request->input('ids'), fn ($q, $ids) => $q->whereIn('id', (array) $ids))
            ->orderBy('employee_id')->get();

        return view('print.salary-notice', ['notices' => $notices, 'settings' => Setting::allValues()]);
    }

    /**
     * Reads "grade, step1 … step8" rows (the usual salary schedule layout). Amounts may have commas.
     *
     * @return list<array{grade: int, step: int, amount: float}>
     */
    private function parseCsv(string $content): array
    {
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $lines = preg_split('/\r\n|\r|\n/', trim($content));
        $delimiter = substr_count($lines[0] ?? '', ';') > substr_count($lines[0] ?? '', ',') ? ';' : ',';
        $rows = [];
        foreach ($lines as $i => $line) {
            $cells = str_getcsv($line, $delimiter);
            $grade = (int) preg_replace('/\D/', '', (string) ($cells[0] ?? ''));
            if ($grade < 1 || $grade > 33) {
                if ($i === 0) {
                    continue; // header
                }
                throw ValidationException::withMessages(['file' => 'Line '.($i + 1).': the first column must be a salary grade from 1 to 33.']);
            }
            for ($step = 1; $step <= 8; $step++) {
                $raw = str_replace([',', '₱', ' '], '', (string) ($cells[$step] ?? ''));
                if ($raw !== '') {
                    if (! is_numeric($raw)) {
                        throw ValidationException::withMessages(['file' => 'Line '.($i + 1).", step {$step}: “{$cells[$step]}” is not an amount."]);
                    }
                    $rows[] = ['grade' => $grade, 'step' => $step, 'amount' => (float) $raw];
                }
            }
        }
        if (! $rows) {
            throw ValidationException::withMessages(['file' => 'No amounts found. Use the template: grade, step1 … step8.']);
        }

        return $rows;
    }

    private function saveRates(SalarySchedule $schedule, array $rows): void
    {
        foreach (array_chunk($rows, 200) as $chunk) {
            SalaryRate::insert(array_map(fn ($r) => $r + ['salary_schedule_id' => $schedule->id], $chunk));
        }
    }
}
