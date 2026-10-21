<?php

namespace App\Http\Controllers\Me;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\PassSlip;
use App\Support\Notify;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** The employee's own pass slips: request, then tap "Leaving now" and "I'm back". */
class PassSlipController extends Controller
{
    public function index(Request $request)
    {
        $employee = $request->user()->employee;

        return view('me.pass-slips', [
            'slips' => PassSlip::where('employee_id', $employee->id)->latest('date')->latest('id')->limit(40)->get(),
            'used' => PassSlip::personalCount($employee, CarbonImmutable::today()),
            'limits' => config('hris.pass_slips'),
        ]);
    }

    public function store(Request $request)
    {
        $employee = $request->user()->employee;
        $data = self::validated($request, $employee, true);
        $slip = PassSlip::create($data + ['employee_id' => $employee->id, 'status' => 'pending', 'requested_by' => $request->user()->id]);
        AuditLog::record('pass_slip.requested', $slip, $data);
        Notify::approver($employee, "Pass slip: {$employee->display_name}", [
            "{$employee->display_name} asks to leave the office {$slip->date->format('D, M j')}, {$slip->times_label} ({$slip->type_name}).",
            "Destination: {$slip->destination}. Purpose: {$slip->purpose}",
        ]);

        return back()->with('status', 'Pass slip sent to your supervisor.');
    }

    public function cancel(Request $request, PassSlip $slip)
    {
        $this->own($request, $slip);
        abort_unless(in_array($slip->status, ['pending', 'approved'], true) && ! $slip->actual_out, 403);
        $slip->update(['status' => 'cancelled']);

        return back()->with('status', 'Pass slip cancelled.');
    }

    /** "Leaving now" / "I'm back": records the actual time, on the day itself. */
    public function clock(Request $request, PassSlip $slip)
    {
        $this->own($request, $slip);
        $which = $request->validate(['event' => ['required', 'in:out,back']])['event'];
        abort_unless($slip->status === 'approved' && $slip->date->isToday(), 403, 'Only an approved pass slip for today.');
        $now = now()->format('H:i');
        if ($which === 'out') {
            abort_if((bool) $slip->actual_out, 422);
            $slip->update(['actual_out' => $now]);
        } else {
            abort_unless($slip->actual_out && ! $slip->actual_back, 422);
            $slip->update(['actual_back' => $now]);
        }
        AuditLog::record('pass_slip.'.$which, $slip, ['time' => $now]);

        return back()->with('status', $which === 'out' ? 'Time out recorded: '.now()->format('g:i A').'. Tap “I’m back” when you return.' : 'Welcome back. Return recorded: '.now()->format('g:i A').'.');
    }

    /** Shared by the employee's form and HR's form. */
    public static function validated(Request $request, Employee $employee, bool $ownRequest, ?PassSlip $slip = null): array
    {
        $data = $request->validate([
            'date' => ['required', 'date', ...($ownRequest ? ['after_or_equal:today'] : [])],
            'type' => ['required', Rule::in(array_keys(PassSlip::TYPES))],
            'time_out' => ['required', 'date_format:H:i'],
            'time_back' => ['nullable', 'date_format:H:i', 'after:time_out'],
            'destination' => ['required', 'string', 'max:200'],
            'purpose' => ['required', 'string', 'max:250'],
        ], ['date.after_or_equal' => 'Pass slips are filed before leaving. Ask HR to record a past one.']);

        if ($data['type'] === 'personal') {
            $limits = config('hris.pass_slips');
            $draft = new PassSlip($data);
            if ($draft->returns() && $draft->minutesOut() > $limits['personal_max_minutes']) {
                throw ValidationException::withMessages(['time_back' => 'A personal pass slip is for up to '.($limits['personal_max_minutes'] / 60).' hours. File leave instead.']);
            }
            $month = CarbonImmutable::parse($data['date']);
            if (PassSlip::personalCount($employee, $month, $slip?->id) >= $limits['personal_per_month']) {
                throw ValidationException::withMessages(['type' => "You've used the {$limits['personal_per_month']} personal pass slips allowed in ".$month->format('F').'. File leave instead.']);
            }
        }

        return $data;
    }

    private function own(Request $request, PassSlip $slip): void
    {
        abort_unless($slip->employee_id === $request->user()->employee?->id, 403);
    }
}
