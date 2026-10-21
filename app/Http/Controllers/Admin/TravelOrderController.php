<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Me\RequestController;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\TravelOrder;
use App\Support\Notify;
use Illuminate\Http\Request;

class TravelOrderController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'pending');

        return view('admin.travel.index', [
            'status' => $status,
            'orders' => TravelOrder::with('employees', 'approver:id,name')
                ->when($status !== 'all', fn ($q) => $q->where('status', $status))
                ->latest('date_from')->paginate(30)->withQueryString(),
            'employees' => Employee::where('is_active', true)->orderBy('last_name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = RequestController::validateTravel($request);
        $ids = $request->validate(['employee_ids' => ['required', 'array', 'min:1'], 'employee_ids.*' => ['exists:employees,id'], 'reference_no' => ['nullable', 'string', 'max:60']]);
        $order = TravelOrder::create($data + [
            'reference_no' => $ids['reference_no'] ?? null, 'status' => 'approved',
            'requested_by' => $request->user()->id, 'approved_by' => $request->user()->id, 'approved_at' => now(),
        ]);
        $order->employees()->sync($ids['employee_ids']);
        AuditLog::record('travel.created', $order, $data + ['employees' => $ids['employee_ids']]);
        foreach ($order->employees as $e) {
            Notify::employee($e, "{$order->type_name} issued", ["{$order->destination}, {$order->date_from->format('M j')}".($order->date_to->ne($order->date_from) ? '–'.$order->date_to->format('M j') : '').'.', 'Purpose: '.$order->purpose], route('me.travel'));
        }

        return back()->with('status', $order->type_name.' issued for '.count($ids['employee_ids']).' employee(s).');
    }

    public function decide(Request $request, TravelOrder $order)
    {
        $data = $request->validate(['decision' => ['required', 'in:approve,disapprove,cancel'], 'remarks' => ['nullable', 'string', 'max:250']]);
        $status = ['approve' => 'approved', 'disapprove' => 'disapproved', 'cancel' => 'cancelled'][$data['decision']];
        $order->update(['status' => $status, 'approved_by' => $request->user()->id, 'approved_at' => now(), 'remarks' => $data['remarks'] ?? $order->remarks]);
        AuditLog::record('travel.'.$status, $order);
        foreach ($order->employees as $e) {
            Notify::employee($e, "Your {$order->type_name} was {$status}", ["{$order->destination}, {$order->date_from->format('M j')}".($order->date_to->ne($order->date_from) ? '–'.$order->date_to->format('M j') : '').'.'], route('me.travel'));
        }

        return back()->with('status', $order->type_name.' '.$status.'.');
    }
}
