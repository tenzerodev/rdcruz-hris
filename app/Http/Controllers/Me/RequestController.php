<?php

namespace App\Http\Controllers\Me;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\OvertimeAuthorization;
use App\Models\Setting;
use App\Models\TravelOrder;
use App\Services\DtrBuilder;
use App\Support\Notify;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** The employee's own overtime requests and travel/OB requests. */
class RequestController extends Controller
{
    public function overtime(Request $request)
    {
        $employee = $request->user()->employee;

        return view('me.overtime', [
            'employee' => $employee,
            'requests' => $employee->overtimeAuthorizations()->latest('date_from')->limit(50)->get(),
            'minHours' => (int) Setting::get('ot_min_minutes', '120') / 60,
        ]);
    }

    public function storeOvertime(Request $request, DtrBuilder $dtr)
    {
        $employee = $request->user()->employee;
        abort_unless($employee->overtimeEligible(), 403, $employee->type_label.' personnel cannot claim overtime.');

        $min = (int) Setting::get('ot_min_minutes', '120') / 60;
        $data = $request->validate([
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'hours' => ['required', 'numeric', 'min:'.$min, 'max:16'],
            'purpose' => ['required', 'string', 'max:250'],
        ], ['hours.min' => "Overtime must be at least $min hours a day to be claimed."]);

        // Weekend OT is not allowed after a leave or absence that same week: warn up front when we already know.
        for ($d = CarbonImmutable::parse($data['date_from']); $d->lte($data['date_to']); $d = $d->addDay()) {
            if (($d->isSaturday() || $d->isSunday()) && $d->startOfWeek()->lte(today())) {
                $dtr->build($employee, $d->year, $d->month); // loads the week
                if ($blocker = $dtr->weekBlocker($d)) {
                    throw ValidationException::withMessages(['date_from' => $d->format('D, M j').': '.$blocker]);
                }
            }
        }

        $ot = OvertimeAuthorization::create([
            'employee_id' => $employee->id,
            'date_from' => $data['date_from'],
            'date_to' => $data['date_to'],
            'max_minutes_per_day' => (int) round($data['hours'] * 60),
            'purpose' => $data['purpose'],
            'status' => 'pending',
            'requested_by' => $request->user()->id,
        ]);
        AuditLog::record('overtime.requested', $ot, $data);
        Notify::approver($employee, "Overtime request: {$employee->display_name}", [
            "{$employee->display_name} requests up to {$data['hours']} hour(s) of overtime a day, {$ot->date_from->format('D, M j')}".($ot->date_to->ne($ot->date_from) ? ' to '.$ot->date_to->format('D, M j') : '').'.',
            'Work to be done: '.$data['purpose'],
        ]);

        return back()->with('status', 'Overtime request sent to your supervisor.');
    }

    public function cancelOvertime(Request $request, OvertimeAuthorization $authorization)
    {
        abort_unless($authorization->employee_id === $request->user()->employee?->id && $authorization->status === 'pending', 403);
        $authorization->update(['status' => 'cancelled']);

        return back()->with('status', 'Overtime request cancelled.');
    }

    public function travel(Request $request)
    {
        $employee = $request->user()->employee;

        return view('me.travel', [
            'employee' => $employee,
            'orders' => $employee->travelOrders()->latest('date_from')->limit(50)->get(),
        ]);
    }

    public function storeTravel(Request $request)
    {
        $data = self::validateTravel($request);
        $order = TravelOrder::create($data + ['status' => 'pending', 'requested_by' => $request->user()->id]);
        $order->employees()->attach($request->user()->employee->id);
        AuditLog::record('travel.requested', $order, $data);
        $me = $request->user()->employee;
        Notify::approver($me, "{$order->type_name} request: {$me->display_name}", [
            "{$order->type_name} to {$order->destination}, {$order->date_from->format('M j')}".($order->date_to->ne($order->date_from) ? '–'.$order->date_to->format('M j') : '').'.',
            'Purpose: '.$order->purpose,
        ]);

        return back()->with('status', 'Request sent to your supervisor.');
    }

    public function cancelTravel(Request $request, TravelOrder $order)
    {
        abort_unless($order->status === 'pending' && $order->requested_by === $request->user()->id, 403);
        $order->update(['status' => 'cancelled']);

        return back()->with('status', 'Request cancelled.');
    }

    public static function validateTravel(Request $request): array
    {
        return $request->validate([
            'type' => ['required', Rule::in(array_keys(TravelOrder::TYPES))],
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'time_from' => ['nullable', 'date_format:H:i', 'required_with:time_to'],
            'time_to' => ['nullable', 'date_format:H:i', 'required_with:time_from', 'after:time_from'],
            'destination' => ['required', 'string', 'max:200'],
            'purpose' => ['required', 'string', 'max:250'],
        ]);
    }
}
