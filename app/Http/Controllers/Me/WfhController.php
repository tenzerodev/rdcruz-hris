<?php

namespace App\Http\Controllers\Me;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Holiday;
use App\Models\IssuedDevice;
use App\Models\WfhArrangement;
use App\Models\WfhReport;
use App\Support\DeviceCookie;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Validation\ValidationException;

/** The employee's work-from-home schedule, accomplishment reports and issued computers. */
class WfhController extends Controller
{
    public function index(Request $request)
    {
        $e = $request->user()->employee;
        $month = $request->date('month', 'Y-m') ?? now()->startOfMonth();
        $arrangements = $e->wfhArrangements()->active()->whereDate('date_to', '>=', today()->subMonths(2))->orderBy('date_from')->get();

        return view('me.wfh', [
            'e' => $e,
            'month' => $month,
            'arrangements' => $arrangements,
            'days' => $this->wfhDays($e, $month),
            'reports' => WfhReport::where('employee_id', $e->id)->whereYear('work_date', $month->year)->whereMonth('work_date', $month->month)->get()->keyBy(fn ($r) => $r->work_date->toDateString()),
            'today' => WfhArrangement::forDay($e, now()),
        ]);
    }

    public function saveReport(Request $request)
    {
        $e = $request->user()->employee;
        $data = $request->validate([
            'work_date' => ['required', 'date', 'before_or_equal:today'],
            'accomplishments' => ['required', 'string', 'max:5000'],
        ]);
        $date = $request->date('work_date');
        if (! WfhArrangement::forDay($e, $date)) {
            throw ValidationException::withMessages(['work_date' => 'That date is not one of your work-from-home days.']);
        }
        $report = WfhReport::where('employee_id', $e->id)->whereDate('work_date', $date->toDateString())->first()
            ?? new WfhReport(['employee_id' => $e->id, 'work_date' => $date->toDateString()]);
        $report->fill(['accomplishments' => $data['accomplishments']])->save();

        return back()->with('status', 'Accomplishments for '.$date->format('M j').' saved.');
    }

    public function devices(Request $request)
    {
        $e = $request->user()->employee;

        return view('me.devices', [
            'devices' => $e->issuedDevices()->orderBy('name')->get(),
            'here' => DeviceCookie::device($request),
            'e' => $e,
        ]);
    }

    /** Registers this browser as the employee's issued computer, using the one-time code from HR. */
    public function register(Request $request)
    {
        $request->validate(['code' => ['required', 'string', 'max:20']]);
        $e = $request->user()->employee;
        $device = IssuedDevice::findByCode($request->input('code'));
        if (! $device || $device->employee_id !== $e->id) {
            AuditLog::record('device.activation_failed', $e);
            throw ValidationException::withMessages(['code' => 'That code is not valid, has expired, or belongs to someone else. Ask HR for a new code.']);
        }
        $token = $device->activate($request->ip(), $request->userAgent());
        AuditLog::record('device.activated', $device, ['employee' => $e->id]);

        return redirect()->route('me.devices')->withCookie(DeviceCookie::make($device, $token))
            ->with('status', "This computer is now registered as “{$device->name}”. You can time in from it on work-from-home days.");
    }

    public function forget(Request $request)
    {
        return redirect()->route('me.devices')->withCookie(Cookie::forget(DeviceCookie::NAME))
            ->with('status', 'This browser is no longer registered. Ask HR for a new code to register it again.');
    }

    /**
     * WFH work days in the month (up to today), for the accomplishment list.
     *
     * @return list<CarbonImmutable>
     */
    private function wfhDays($e, $month): array
    {
        $start = $month->copy()->startOfMonth()->toImmutable();
        $end = min($start->endOfMonth(), now()->toImmutable());
        $arrangements = $e->wfhArrangements()->active()->whereDate('date_from', '<=', $end)->whereDate('date_to', '>=', $start)->get();
        $holidays = Holiday::whereDate('date', '>=', $start)->whereDate('date', '<=', $end)->get()->map(fn ($h) => $h->date->toDateString())->all();
        $days = [];
        for ($d = $start; $d <= $end; $d = $d->addDay()) {
            if (! $d->isSunday() && ! in_array($d->toDateString(), $holidays, true) && $arrangements->contains(fn ($a) => $a->covers($d))) {
                $days[] = $d;
            }
        }

        return array_reverse($days);
    }
}
