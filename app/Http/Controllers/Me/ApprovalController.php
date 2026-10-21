<?php

namespace App\Http\Controllers\Me;

use App\Http\Controllers\Admin\PassSlipController;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\LeaveApplication;
use App\Models\OvertimeAuthorization;
use App\Models\PassSlip;
use App\Models\TravelOrder;
use App\Services\LeaveService;
use App\Support\Notify;
use Illuminate\Http\Request;

/** Supervisor inbox: leave to recommend, overtime and travel/OB to approve, for direct reports. */
class ApprovalController extends Controller
{
    public function index(Request $request)
    {
        $ids = $request->user()->employee->subordinates()->pluck('id');

        return view('me.approvals', [
            'leaves' => LeaveApplication::with('employee')->whereIn('employee_id', $ids)->where('status', 'pending')->orderBy('date_from')->get(),
            'overtime' => OvertimeAuthorization::with('employee')->whereIn('employee_id', $ids)->where('status', 'pending')->orderBy('date_from')->get(),
            'travel' => TravelOrder::with('employees')->where('status', 'pending')
                ->whereHas('employees', fn ($q) => $q->whereIn('employees.id', $ids))->orderBy('date_from')->get(),
            'passSlips' => PassSlip::with('employee')->whereIn('employee_id', $ids)->where('status', 'pending')->orderBy('date')->get(),
            'team' => $request->user()->employee->subordinates()->where('is_active', true)->orderBy('last_name')->get(),
        ]);
    }

    public function leave(Request $request, LeaveApplication $leave, LeaveService $leaves)
    {
        abort_unless($request->user()->canActFor($leave->employee), 403);
        $data = $request->validate(['decision' => ['required', 'in:recommend,disapprove'], 'remarks' => ['nullable', 'required_if:decision,disapprove', 'string', 'max:250']]);
        $leaves->recommend($leave, $request->user(), $data['decision'] === 'recommend', $data['remarks'] ?? null);

        return back()->with('status', $data['decision'] === 'recommend' ? 'Recommended for approval. HR will finalize it.' : 'Leave disapproved.');
    }

    public function overtime(Request $request, OvertimeAuthorization $authorization)
    {
        abort_unless($request->user()->canActFor($authorization->employee) && $authorization->status === 'pending', 403);
        $data = $request->validate(['decision' => ['required', 'in:approve,disapprove'], 'remarks' => ['nullable', 'string', 'max:250']]);
        $authorization->update([
            'status' => $data['decision'] === 'approve' ? 'approved' : 'disapproved',
            'approved_by' => $request->user()->id, 'decided_at' => now(), 'remarks' => $data['remarks'] ?? null,
        ]);
        AuditLog::record('overtime.'.$authorization->status, $authorization);
        Notify::employee($authorization->employee, 'Your overtime request was '.$authorization->status, [
            "Overtime {$authorization->date_from->format('M j')}".($authorization->date_to->ne($authorization->date_from) ? '–'.$authorization->date_to->format('M j') : '').": {$authorization->purpose}.",
            $authorization->remarks ? 'Remarks: '.$authorization->remarks : 'Remember: at least 2 hours a day is needed to claim it.',
        ], route('me.overtime'));

        return back()->with('status', 'Overtime request '.$authorization->status.'.');
    }

    public function travel(Request $request, TravelOrder $order)
    {
        abort_unless($order->status === 'pending' && $order->employees->every(fn ($e) => $request->user()->canActFor($e)), 403);
        $data = $request->validate(['decision' => ['required', 'in:approve,disapprove'], 'remarks' => ['nullable', 'string', 'max:250']]);
        $order->update([
            'status' => $data['decision'] === 'approve' ? 'approved' : 'disapproved',
            'approved_by' => $request->user()->id, 'approved_at' => now(), 'remarks' => $data['remarks'] ?? null,
        ]);
        AuditLog::record('travel.'.$order->status, $order);
        foreach ($order->employees as $e) {
            Notify::employee($e, "Your {$order->type_name} was {$order->status}", ["{$order->destination}, {$order->date_from->format('M j')}".($order->date_to->ne($order->date_from) ? '–'.$order->date_to->format('M j') : '').'.'], route('me.travel'));
        }

        return back()->with('status', $order->type_name.' '.$order->status.'.');
    }

    public function passSlip(Request $request, PassSlip $slip)
    {
        abort_unless($request->user()->canActFor($slip->employee) && $slip->status === 'pending', 403);
        $data = $request->validate(['decision' => ['required', 'in:approve,disapprove'], 'remarks' => ['nullable', 'string', 'max:250']]);
        PassSlipController::decideSlip($slip, $data['decision'] === 'approve' ? 'approved' : 'disapproved', $request->user()->id, $data['remarks'] ?? null);

        return back()->with('status', 'Pass slip '.($data['decision'] === 'approve' ? 'approved' : 'disapproved').'.');
    }
}
