<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\IssuedDevice;
use App\Models\WfhArrangement;
use App\Models\WfhReport;
use App\Support\Notify;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** HR sets work-from-home arrangements and registers agency-issued computers. */
class WfhController extends Controller
{
    public function index(Request $request)
    {
        $show = $request->input('show', 'current');
        $arrangements = WfhArrangement::with('employee', 'creator')->active()
            ->when($show === 'current', fn ($q) => $q->whereDate('date_to', '>=', today()))
            ->when($request->integer('employee_id'), fn ($q, $id) => $q->where('employee_id', $id))
            ->orderBy('date_from')->paginate(50)->withQueryString();
        $employees = Employee::where('is_active', true)->orderBy('last_name')->orderBy('first_name')->get();
        $today = WfhArrangement::active()->with('employee')->whereDate('date_from', '<=', today())->whereDate('date_to', '>=', today())
            ->get()->filter(fn ($a) => $a->covers(today()))->unique('employee_id');

        return view('admin.wfh.index', [
            'arrangements' => $arrangements,
            'employees' => $employees,
            'today' => $today,
            'show' => $show,
            'devicesByEmployee' => IssuedDevice::where('is_active', true)->whereNotNull('activated_at')->get()->groupBy('employee_id'),
            'recentReports' => WfhReport::with('employee')->latest('work_date')->latest('id')->limit(10)->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_ids' => ['required', 'array', 'min:1'],
            'employee_ids.*' => ['integer', 'exists:employees,id'],
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'weekdays' => ['nullable', 'array'],
            'weekdays.*' => ['integer', Rule::in(array_keys(WfhArrangement::DAYS))],
            'reference_no' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:250'],
        ]);
        $weekdays = array_values(array_unique(array_map('intval', $data['weekdays'] ?? [])));
        sort($weekdays);

        if ($request->boolean('restrict_login')) {
            Employee::whereIn('id', $data['employee_ids'])->update(['login_device_only' => true]);
            AuditLog::record('device.login_restricted', null, ['employees' => $data['employee_ids']]);
        }

        foreach ($data['employee_ids'] as $id) {
            $a = WfhArrangement::create([
                'employee_id' => $id, 'date_from' => $data['date_from'], 'date_to' => $data['date_to'],
                'weekdays' => $weekdays ?: null, 'require_device' => $request->boolean('require_device'),
                'reference_no' => $data['reference_no'] ?? null, 'notes' => $data['notes'] ?? null, 'created_by' => $request->user()->id,
            ]);
            AuditLog::record('wfh.created', $a, ['employee' => $id]);
            Notify::employee($a->employee, 'Work-from-home arrangement', [
                'You may work from home '.$a->date_from->format('M j, Y').' to '.$a->date_to->format('M j, Y').' ('.$a->days_label.').',
                $a->require_device ? 'Time in and out from your agency-issued computer, and write your daily accomplishments in the HRIS.' : 'Time in and out in the HRIS, and write your daily accomplishments.',
            ], route('me.wfh'));
        }

        return back()->with('status', 'Work-from-home arrangement saved for '.count($data['employee_ids']).' employee(s).');
    }

    public function cancel(WfhArrangement $arrangement)
    {
        if ($arrangement->date_to->lt(today())) {
            return back()->with('status', 'That arrangement has already ended.');
        }
        // Ending early keeps past WFH days on record; a future arrangement is cancelled outright.
        if ($arrangement->date_from->lte(today()->subDay())) {
            $arrangement->update(['date_to' => today()->subDay()]);
            $message = 'Arrangement ended as of yesterday. Past work-from-home days stay on record.';
        } else {
            $arrangement->update(['cancelled_at' => now()]);
            $message = 'Arrangement cancelled.';
        }
        AuditLog::record('wfh.ended', $arrangement);

        return back()->with('status', $message);
    }

    public function devices(Request $request)
    {
        return view('admin.wfh.devices', [
            'devices' => IssuedDevice::with('employee')->orderByDesc('is_active')->orderBy('name')->paginate(50),
            'restricted' => Employee::where('login_device_only', true)->with(['issuedDevices' => fn ($q) => $q->where('is_active', true)])->orderBy('last_name')->get(),
            'employees' => Employee::where('is_active', true)->orderBy('last_name')->orderBy('first_name')->get(),
        ]);
    }

    public function storeDevice(Request $request)
    {
        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'name' => ['required', 'string', 'max:150'],
            'property_no' => ['nullable', 'string', 'max:100'],
            'serial_no' => ['nullable', 'string', 'max:100'],
        ]);
        $device = IssuedDevice::create([...$data, 'created_by' => $request->user()->id]);
        if ($request->boolean('restrict_login')) {
            $device->employee->forceFill(['login_device_only' => true])->save();
            AuditLog::record('device.login_restricted', $device->employee);
        }
        $code = $device->newActivationCode();
        AuditLog::record('device.created', $device, ['employee' => $device->employee_id]);

        return back()->with('code', ['code' => $code, 'device' => $device->name, 'employee' => $device->employee->full_name]);
    }

    public function newCode(IssuedDevice $device)
    {
        $code = $device->newActivationCode();
        AuditLog::record('device.code_reissued', $device);

        return back()->with('code', ['code' => $code, 'device' => $device->name, 'employee' => $device->employee->full_name]);
    }

    public function toggleDevice(IssuedDevice $device)
    {
        $device->forceFill(['is_active' => ! $device->is_active])->save();
        AuditLog::record($device->is_active ? 'device.enabled' : 'device.disabled', $device);

        return back()->with('status', $device->is_active ? "{$device->name} can be used again." : "{$device->name} is disabled. Punches from it are refused.");
    }

    /** Turn "sign in only from issued computers" on or off for an employee. */
    public function restrictLogin(Request $request, Employee $employee)
    {
        $on = $request->boolean('on');
        $employee->forceFill(['login_device_only' => $on])->save();
        AuditLog::record($on ? 'device.login_restricted' : 'device.login_unrestricted', $employee);

        return back()->with('status', $on
            ? "{$employee->full_name} can now sign in only from their issued computer."
            : "{$employee->full_name} can sign in from any device again.");
    }

    public function destroyDevice(IssuedDevice $device)
    {
        AuditLog::record('device.deleted', $device, ['name' => $device->name, 'employee' => $device->employee_id]);
        $device->delete();

        return back()->with('status', 'Device removed.');
    }
}
