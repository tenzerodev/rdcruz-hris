<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Clearance;
use App\Models\ClearanceItem;
use App\Models\Employee;
use App\Models\Setting;
use App\Services\ClearanceService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ClearanceController extends Controller
{
    public function __construct(private ClearanceService $service) {}

    public function index(Request $request)
    {
        $status = $request->input('status', 'open');

        return view('admin.clearances.index', [
            'clearances' => Clearance::with(['employee', 'items'])->when($status !== 'all', fn ($q) => $q->where('status', $status))->latest()->paginate(30)->withQueryString(),
            'employees' => Employee::where('is_active', true)->orderBy('last_name')->orderBy('first_name')->get(),
            'status' => $status,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'separation_date' => ['required', 'date'],
            'reason' => ['required', 'string', 'max:150'],
        ]);
        $c = $this->service->start(Employee::findOrFail($data['employee_id']), Carbon::parse($data['separation_date']), $data['reason'], $request->user());

        return redirect()->route('admin.clearances.show', $c)->with('status', 'Clearance started. Record each office’s sign-off as it comes in.');
    }

    public function show(Clearance $clearance)
    {
        return view('admin.clearances.show', ['c' => $clearance->load('employee.issuedDevices', 'items')]);
    }

    public function sign(Request $request, ClearanceItem $item)
    {
        $data = $request->validate(['cleared_by' => ['required', 'string', 'max:150'], 'remarks' => ['nullable', 'string', 'max:250']]);
        $this->service->sign($item, $data['cleared_by'], $data['remarks'] ?? null, $request->user());

        return back()->with('status', "{$item->office} cleared.");
    }

    public function unsign(ClearanceItem $item)
    {
        $this->service->unsign($item);

        return back()->with('status', "{$item->office} sign-off removed.");
    }

    public function complete(Request $request, Clearance $clearance)
    {
        $this->service->complete($clearance, $request->user());

        return back()->with('status', 'Clearance completed. The employee is separated: login and issued computers disabled, service record closed. Next: terminal leave and last pay.');
    }

    public function cancel(Clearance $clearance)
    {
        $this->service->cancel($clearance);

        return redirect()->route('admin.clearances.index')->with('status', 'Clearance cancelled.');
    }

    public function print(Request $request, Clearance $clearance)
    {
        $user = $request->user();
        abort_unless($user->canSee('people') || $user->employee?->id === $clearance->employee_id, 404);

        return view('print.clearance', ['c' => $clearance->load('employee', 'items'), 'settings' => Setting::allValues()]);
    }
}
