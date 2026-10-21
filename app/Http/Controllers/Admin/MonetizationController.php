<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\LeaveMonetization;
use App\Models\Setting;
use App\Services\LeaveService;
use App\Services\MonetizationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MonetizationController extends Controller
{
    public function __construct(private MonetizationService $service) {}

    public function index(Request $request, LeaveService $leaves)
    {
        $employees = Employee::orderBy('last_name')->orderBy('first_name')->get()->filter->earnsLeave()->values();
        $selected = $request->integer('employee_id') ? $employees->firstWhere('id', $request->integer('employee_id')) : null;

        return view('admin.leave.monetization', [
            'employees' => $employees,
            'selected' => $selected,
            'balances' => $selected ? $leaves->balances($selected) : null,
            'terminal' => $selected ? $this->service->terminalPreview($selected) : null,
            'records' => LeaveMonetization::with('employee')->latest('date')->latest('id')->paginate(30),
            'factor' => MonetizationService::FACTOR,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'kind' => ['required', Rule::in(array_keys(LeaveMonetization::KINDS))],
            'date' => ['required', 'date'],
            'vl_days' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'sl_days' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'reason' => ['nullable', 'string', 'max:250'],
        ]);
        $m = $this->service->monetize(Employee::findOrFail($data['employee_id']), $data['kind'], (float) ($data['vl_days'] ?? 0), (float) ($data['sl_days'] ?? 0),
            Carbon::parse($data['date']), $data['reason'] ?? null, $request->user());

        return redirect()->route('admin.leave.monetization', ['employee_id' => $m->employee_id])
            ->with('status', "{$m->kind_name}: ₱".number_format($m->amount, 2).' for '.($m->vl_days + $m->sl_days).' day(s). Charged to the leave card.')
            ->with('print', route('admin.leave.monetization.print', $m));
    }

    public function destroy(LeaveMonetization $monetization)
    {
        $this->service->undo($monetization);

        return back()->with('status', 'Cancelled; the leave credits were restored.');
    }

    public function print(LeaveMonetization $monetization)
    {
        return view('print.monetization', ['m' => $monetization->load('employee', 'creator'), 'settings' => Setting::allValues()]);
    }
}
