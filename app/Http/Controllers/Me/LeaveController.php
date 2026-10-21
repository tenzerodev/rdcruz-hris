<?php

namespace App\Http\Controllers\Me;

use App\Http\Controllers\Controller;
use App\Models\LeaveApplication;
use App\Services\LeaveService;
use Illuminate\Http\Request;

class LeaveController extends Controller
{
    public function index(Request $request, LeaveService $leaves)
    {
        $employee = $request->user()->employee;

        return view('me.leave', [
            'employee' => $employee,
            'balances' => $employee->earnsLeave() ? $leaves->balances($employee) : null,
            'wellness' => $leaves->wellnessEligible($employee) ? $leaves->wellness($employee, now()->year) : null,
            'applications' => $employee->leaveApplications()->limit(50)->get(),
            'types' => self::typesFor($employee),
        ]);
    }

    public function store(Request $request, LeaveService $leaves)
    {
        $data = self::validateLeave($request);
        $leaves->file($request->user()->employee, $data, $request->user());

        return redirect()->route('me.leave')->with('status', 'Leave application filed. Your supervisor will be notified on their Approvals page.');
    }

    public function cancel(Request $request, LeaveApplication $leave, LeaveService $leaves)
    {
        abort_unless($leave->employee_id === $request->user()->employee?->id, 403);
        abort_unless($leave->isOpen(), 422, 'Only pending applications can be cancelled here. Ask HR to cancel an approved leave.');
        $leaves->cancel($leave, $request->user(), 'Cancelled by employee');

        return back()->with('status', 'Leave application cancelled.');
    }

    public static function typesFor($employee): array
    {
        return collect(config('hris.leave_types'))
            ->filter(fn ($t) => empty($t['wellness'])
                ? ($employee->earnsLeave() || $t['charge'] === null)
                : in_array($employee->employment_type, config('hris.wellness.types'), true))
            ->map(fn ($t) => $t['name'])->all();
    }

    public static function validateLeave(Request $request): array
    {
        return $request->validate([
            'type' => ['required', 'in:'.implode(',', array_keys(config('hris.leave_types')))],
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'half_day' => ['nullable', 'in:am,pm'],
            'details.location' => ['nullable', 'in:ph,abroad'],
            'details.abroad_where' => ['nullable', 'string', 'max:150'],
            'details.sick' => ['nullable', 'in:hospital,outpatient'],
            'details.illness' => ['nullable', 'string', 'max:150'],
            'details.women_illness' => ['nullable', 'string', 'max:150'],
            'details.study' => ['nullable', 'in:masters,bar_board'],
            'details.other' => ['nullable', 'string', 'max:150'],
            'commutation' => ['nullable', 'boolean'],
            'reason' => ['nullable', 'string', 'max:250'],
        ]);
    }
}
