<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Me\LeaveController as MeLeave;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Models\LeaveLedger;
use App\Services\LeaveService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LeaveController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'open');

        return view('admin.leave.index', [
            'status' => $status,
            'applications' => LeaveApplication::with('employee', 'recommender:id,name')
                ->when($status === 'open', fn ($q) => $q->whereIn('status', ['pending', 'recommended']),
                    fn ($q) => $q->when($status !== 'all', fn ($q) => $q->where('status', $status)))
                ->when($request->integer('employee_id'), fn ($q, $id) => $q->where('employee_id', $id))
                ->orderByRaw("case status when 'recommended' then 0 when 'pending' then 1 else 2 end")
                ->latest('date_from')->paginate(30)->withQueryString(),
            'employees' => Employee::where('is_active', true)->orderBy('last_name')->get(),
        ]);
    }

    /** HR files on behalf of an employee (e.g. sick leave filed on return). */
    public function store(Request $request, LeaveService $leaves)
    {
        $request->validate(['employee_id' => ['required', 'exists:employees,id']]);
        $employee = Employee::findOrFail($request->integer('employee_id'));
        $leave = $leaves->file($employee, MeLeave::validateLeave($request), $request->user());
        if ($request->boolean('approve_now')) {
            $leaves->approve($leave, $request->user(), 'Filed and approved by HR');
        }

        return back()->with('status', 'Leave filed for '.$employee->display_name.'.');
    }

    public function decide(Request $request, LeaveApplication $leave, LeaveService $leaves)
    {
        $data = $request->validate([
            'decision' => ['required', 'in:approve,disapprove,cancel'],
            'remarks' => ['nullable', 'required_if:decision,disapprove', 'string', 'max:250'],
        ]);
        match ($data['decision']) {
            'approve' => $leaves->approve($leave, $request->user(), $data['remarks'] ?? null),
            'disapprove' => $leaves->disapprove($leave, $request->user(), $data['remarks']),
            'cancel' => $leaves->cancel($leave, $request->user(), $data['remarks'] ?? 'Cancelled by HR'),
        };

        return back()->with('status', 'Leave '.$leave->fresh()->status.'.');
    }

    public function card(Request $request, Employee $employee, LeaveService $leaves)
    {
        $year = $request->integer('year') ?: now()->year;

        return view('admin.leave.card', [
            'employee' => $employee,
            'year' => $year,
            'balances' => $leaves->balances($employee),
            'opening' => $this->openingBalances($employee, $year),
            'entries' => LeaveLedger::where('employee_id', $employee->id)->whereYear('entry_date', $year)
                ->orderBy('entry_date')->orderBy('id')->get(),
            'print' => $request->boolean('print'),
        ]);
    }

    public function adjust(Request $request, Employee $employee)
    {
        $data = $request->validate([
            'balance' => ['required', Rule::in(array_keys(config('hris.balances')))],
            'entry_date' => ['required', 'date'],
            'particulars' => ['required', 'string', 'max:200'],
            'earned' => ['nullable', 'numeric', 'min:0'],
            'used' => ['nullable', 'numeric', 'min:0'],
        ]);
        $entry = LeaveLedger::create($data + [
            'employee_id' => $employee->id, 'earned' => $data['earned'] ?? 0, 'used' => $data['used'] ?? 0,
            'source' => 'adjustment', 'created_by' => $request->user()->id,
        ]);
        AuditLog::record('leave.adjusted', $employee, $entry->only('balance', 'particulars', 'earned', 'used'));

        return back()->with('status', 'Leave card updated.');
    }

    public function destroyEntry(LeaveLedger $entry)
    {
        abort_unless($entry->source === 'adjustment', 403, 'Only manual adjustments can be removed. Cancel the leave or re-post the month instead.');
        AuditLog::record('leave.adjustment_removed', $entry->employee, $entry->only('balance', 'particulars', 'earned', 'used'));
        $entry->delete();

        return back()->with('status', 'Adjustment removed.');
    }

    public function posting()
    {
        return view('admin.leave.posting', [
            'recent' => AuditLog::whereIn('action', ['leave.month_posted', 'leave.year_posted'])->latest()->limit(12)->get(),
        ]);
    }

    public function post(Request $request, LeaveService $leaves)
    {
        $data = $request->validate(['kind' => ['required', 'in:month,year'], 'month' => ['required_if:kind,month', 'nullable', 'date_format:Y-m'], 'year' => ['required_if:kind,year', 'nullable', 'integer', 'min:2000', 'max:2100']]);

        if ($data['kind'] === 'month') {
            [$y, $m] = array_map('intval', explode('-', $data['month']));
            $s = $leaves->postMonth($y, $m, $request->user());

            return back()->with('status', "Posted {$data['month']}: {$s['accrued']} earned credits, {$s['undertime']} undertime charges, {$s['coc']} COC credits.");
        }

        $s = $leaves->postYearEnd((int) $data['year'], $request->user());

        return back()->with('status', "Year-end {$data['year']} posted: {$s['forced']} forced-leave deductions, SPL granted to {$s['spl']}.");
    }

    private function openingBalances(Employee $employee, int $year): array
    {
        $rows = LeaveLedger::where('employee_id', $employee->id)->whereDate('entry_date', '<', "$year-01-01")
            ->selectRaw('balance, sum(earned) - sum(used) as total')->groupBy('balance')->pluck('total', 'balance');

        return collect(config('hris.balances'))->map(fn ($l, $k) => round((float) ($rows[$k] ?? 0), 3))->all();
    }
}
