<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesFacePunch;
use App\Models\Announcement;
use App\Models\AnnouncementRead;
use App\Models\Clearance;
use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Models\OvertimeAuthorization;
use App\Models\PassSlip;
use App\Models\Punch;
use App\Models\Setting;
use App\Models\TravelOrder;
use App\Models\WfhArrangement;
use App\Services\DtrBuilder;
use App\Services\FaceMatcher;
use App\Services\LeaveService;
use App\Services\PunchRecorder;
use App\Support\DeviceCookie;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rules\Password;
use RuntimeException;

class MeController extends Controller
{
    use HandlesFacePunch;

    public function index(Request $request, PunchRecorder $recorder, DtrBuilder $builder)
    {
        $employee = $request->user()->employee;
        $month = $builder->build($employee, now()->year, now()->month);
        $today = Punch::where('employee_id', $employee->id)->whereDate('work_date', today())
            ->orderBy('punched_at')->get();
        $recent = Punch::where('employee_id', $employee->id)->latest('punched_at')->limit(20)->get();

        return view('me.index', [
            'employee' => $employee,
            'today' => $today,
            'recent' => $recent,
            'next' => $recorder->nextType($employee, now()),
            'enrolled' => $employee->faceSamples()->exists(),
            'month' => $month,
            'pendingApprovals' => $request->user()->isSupervisor() ? $this->pendingApprovals($employee) : 0,
            'wfh' => WfhArrangement::forDay($employee, now()),
            'clearance' => Clearance::with('items')->where('employee_id', $employee->id)->where('status', 'open')->first(),
            'device' => DeviceCookie::deviceFor($request, $employee),
            'memos' => $this->unacknowledged($employee),
        ]);
    }

    /** Live memos addressed to the employee that still need their acknowledgment (or haven't been opened). */
    private function unacknowledged(Employee $employee): Collection
    {
        $reads = AnnouncementRead::where('employee_id', $employee->id)->get()->keyBy('announcement_id');

        return Announcement::forEmployee($employee)
            ->filter(fn ($a) => $a->requires_ack ? ! $reads->get($a->id)?->acknowledged_at : ! $reads->has($a->id))->values();
    }

    private function pendingApprovals($employee): int
    {
        $ids = $employee->subordinates()->pluck('id');

        return LeaveApplication::whereIn('employee_id', $ids)->where('status', 'pending')->count()
            + OvertimeAuthorization::whereIn('employee_id', $ids)->where('status', 'pending')->count()
            + TravelOrder::where('status', 'pending')->whereHas('employees', fn ($q) => $q->whereIn('employees.id', $ids))->count()
            + PassSlip::whereIn('employee_id', $ids)->where('status', 'pending')->count();
    }

    public function punchPage(Request $request, PunchRecorder $recorder)
    {
        $employee = $request->user()->employee;

        $wfh = WfhArrangement::forDay($employee, now());

        return view('me.punch', [
            'employee' => $employee,
            'wfh' => $wfh,
            'device' => DeviceCookie::deviceFor($request, $employee),
            'next' => $recorder->nextType($employee, now()),
            'geofence' => Setting::get('geofence_enabled') === '1',
            'enrolled' => $employee->faceSamples()->exists(),
        ]);
    }

    public function punch(Request $request, FaceMatcher $matcher, PunchRecorder $recorder)
    {
        $employee = $request->user()->employee;
        $wfh = WfhArrangement::forDay($employee, now());
        $device = DeviceCookie::deviceFor($request, $employee);

        if (! $employee->allow_personal && ! $wfh) {
            return $this->failed('You are only allowed to punch at an office kiosk.', 403);
        }
        if ($wfh && $wfh->require_device && ! $device) {
            return $this->failed('Work-from-home punches must be made from your agency-issued computer. Register it under "My devices" with the code from HR.', 403);
        }
        if (! $employee->canRecognize()) {
            return $this->failed('Your face is not enrolled yet, or consent has not been recorded. Please see HR.', 403);
        }

        $data = $this->validatePunchRequest($request);

        // The office geofence doesn't apply on a work-from-home day.
        if (! $wfh) {
            try {
                $recorder->assertInsideGeofence(
                    isset($data['latitude']) ? (float) $data['latitude'] : null,
                    isset($data['longitude']) ? (float) $data['longitude'] : null,
                    isset($data['accuracy']) ? (float) $data['accuracy'] : null,
                );
            } catch (RuntimeException $e) {
                return $this->failed($e->getMessage(), 403);
            }
        }

        $result = $matcher->match($data['descriptor'], $employee);
        if (! $result['matched']) {
            return $this->failed("Face doesn't match {$employee->first_name}'s enrolled face. Try again in better light.", 403);
        }

        try {
            $punch = $recorder->record($employee, [
                'source' => $wfh ? 'wfh' : 'personal',
                'issued_device_id' => $device?->id,
                'type' => $data['type'] ?? null,
                'distance' => $result['distance'],
                'liveness' => (bool) ($data['liveness'] ?? false),
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'accuracy' => $data['accuracy'] ?? null,
                'snapshot' => $data['snapshot'] ?? null,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (RuntimeException $e) {
            return $this->failed($e->getMessage(), 409);
        } catch (UniqueConstraintViolationException) {
            return $this->failed('That punch was just recorded.', 409);
        }

        if ($device) {
            $device->forceFill(['last_used_at' => now(), 'last_ip' => $request->ip()])->saveQuietly();
        }

        return $this->punchResponse($punch, $employee->punchLabel($punch->type).' recorded'.($wfh ? ' (work from home)' : '').'.');
    }

    public function dtr(Request $request, DtrBuilder $builder)
    {
        $month = $request->date('month', 'Y-m') ?? now();
        $employee = $request->user()->employee;

        return view('dtr.print', [
            'records' => [$builder->build($employee, $month->year, $month->month)],
            'settings' => Setting::allValues(),
            'backUrl' => route('me.index'),
        ]);
    }

    public function profile(Request $request, LeaveService $leaves)
    {
        $employee = $request->user()->employee->load(['supervisor', 'plantillaPosition', 'serviceRecords', 'educations', 'eligibilities', 'trainings']);

        return view('me.profile', ['employee' => $employee, 'balances' => $employee->earnsLeave() ? $leaves->balances($employee) : null]);
    }

    public function passwordForm()
    {
        return view('me.password');
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);
        $request->user()->forceFill(['password' => $request->input('password'), 'must_change_password' => false, 'password_changed_at' => now()])->save();

        return redirect()->route('me.index')->with('status', 'Password changed.');
    }
}
