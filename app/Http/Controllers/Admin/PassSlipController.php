<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Me\PassSlipController as MinePassSlips;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\PassSlip;
use App\Support\Notify;
use Illuminate\Http\Request;

/** HR / timekeeper view of pass slips: approve when there is no supervisor, record or correct the times. */
class PassSlipController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'pending');
        $date = $request->query('date');

        return view('admin.pass-slips', [
            'status' => $status,
            'date' => $date,
            'slips' => PassSlip::with('employee', 'approver:id,name')
                ->when($status !== 'all', fn ($q) => $q->where('status', $status))
                ->when($date, fn ($q) => $q->whereDate('date', $date))
                ->latest('date')->latest('id')->paginate(40)->withQueryString(),
            'employees' => Employee::where('is_active', true)->orderBy('last_name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $employee = Employee::findOrFail($request->validate(['employee_id' => ['required', 'exists:employees,id']])['employee_id']);
        $data = MinePassSlips::validated($request, $employee, false);
        $times = $request->validate(['actual_out' => ['nullable', 'date_format:H:i'], 'actual_back' => ['nullable', 'date_format:H:i', 'after:actual_out']]);
        $slip = PassSlip::create($data + array_filter($times) + [
            'employee_id' => $employee->id, 'status' => 'approved', 'requested_by' => $request->user()->id,
            'approved_by' => $request->user()->id, 'approved_at' => now(),
        ]);
        AuditLog::record('pass_slip.created', $slip, $data);

        return back()->with('status', "Pass slip recorded for {$employee->full_name}.");
    }

    public function decide(Request $request, PassSlip $slip)
    {
        $data = $request->validate(['decision' => ['required', 'in:approve,disapprove,cancel'], 'remarks' => ['nullable', 'string', 'max:250']]);
        $status = ['approve' => 'approved', 'disapprove' => 'disapproved', 'cancel' => 'cancelled'][$data['decision']];
        self::decideSlip($slip, $status, $request->user()->id, $data['remarks'] ?? null);

        return back()->with('status', 'Pass slip '.$status.'.');
    }

    /** Record or correct the actual times (e.g. from the guard's logbook). */
    public function times(Request $request, PassSlip $slip)
    {
        $data = $request->validate(['actual_out' => ['nullable', 'date_format:H:i'], 'actual_back' => ['nullable', 'date_format:H:i', 'after:actual_out']]);
        $slip->update(['actual_out' => $data['actual_out'] ?? null, 'actual_back' => $data['actual_back'] ?? null]);
        AuditLog::record('pass_slip.times_corrected', $slip, $data);

        return back()->with('status', 'Times saved. The DTR uses them from now on.');
    }

    public static function decideSlip(PassSlip $slip, string $status, int $userId, ?string $remarks): void
    {
        $slip->update(['status' => $status, 'approved_by' => $userId, 'approved_at' => now(), 'remarks' => $remarks ?? $slip->remarks]);
        AuditLog::record('pass_slip.'.$status, $slip);
        Notify::employee($slip->employee, "Your pass slip was {$status}", ["{$slip->date->format('D, M j')}, {$slip->times_label}: {$slip->destination}."], route('me.pass-slips'));
    }
}
