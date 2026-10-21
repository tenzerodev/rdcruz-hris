<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Punch;
use App\Models\User;
use App\Services\LeaveService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q'));
        $employees = Employee::query()
            ->withCount('faceSamples')
            ->with('user:id,username')
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                ->where('last_name', 'like', "%$q%")->orWhere('first_name', 'like', "%$q%")
                ->orWhere('employee_no', 'like', "%$q%")->orWhere('office', 'like', "%$q%")))
            ->when($request->query('status') === 'inactive', fn ($query) => $query->where('is_active', false),
                fn ($query) => $query->where('is_active', true))
            ->when($request->query('group'), fn ($query, $group) => $query->whereIn('employment_type',
                collect(config('hris.employment_types'))->where('group', $group)->keys()))
            ->orderBy('last_name')->orderBy('first_name')
            ->paginate(25)->withQueryString();

        return view('admin.employees.index', compact('employees', 'q'));
    }

    public function create()
    {
        return view('admin.employees.form', ['employee' => new Employee([
            'is_active' => true, 'allow_personal' => true, 'schedule_type' => 'flexi',
            'employment_type' => request('type', 'permanent'),
        ])] + $this->formOptions());
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $employee = DB::transaction(function () use ($data) {
            $employee = Employee::create($data['employee']);
            $this->syncAccount($employee, $data['account']);

            return $employee;
        });
        AuditLog::record('employee.created', $employee, ['employee_no' => $employee->employee_no]);

        return redirect()->route('admin.employees.enroll', $employee)
            ->with('status', 'Employee saved. Now enroll their face.');
    }

    public function edit(Employee $employee)
    {
        $employee->load('user')->loadCount('faceSamples');

        return view('admin.employees.form', ['employee' => $employee] + $this->formOptions($employee));
    }

    public function show(Request $request, Employee $employee, LeaveService $leaves)
    {
        $employee->load(['user', 'supervisor', 'plantillaPosition', 'serviceRecords', 'educations', 'eligibilities', 'trainings', 'documents'])
            ->loadCount('faceSamples');

        return view('admin.employees.show', [
            'employee' => $employee,
            'tab' => $request->query('tab', 'overview'),
            'balances' => $employee->earnsLeave() ? $leaves->balances($employee) : null,
            'leaves' => $employee->leaveApplications()->limit(15)->get(),
        ]);
    }

    private function formOptions(?Employee $employee = null): array
    {
        return [
            'positions' => Position::where('is_active', true)
                ->where(fn ($q) => $q->whereDoesntHave('employee')->when($employee?->position_id, fn ($q, $id) => $q->orWhere('id', $id)))
                ->orderBy('title')->get(),
            'supervisors' => Employee::where('is_active', true)->when($employee, fn ($q) => $q->whereKeyNot($employee->id))
                ->orderBy('last_name')->get(['id', 'last_name', 'first_name', 'middle_name', 'suffix']),
        ];
    }

    public function update(Request $request, Employee $employee)
    {
        $data = $this->validated($request, $employee);

        DB::transaction(function () use ($employee, $data) {
            $employee->update($data['employee']);
            $this->syncAccount($employee, $data['account']);
        });
        AuditLog::record('employee.updated', $employee);

        return redirect()->route('admin.employees.show', $employee)->with('status', 'Employee updated.');
    }

    public function destroy(Employee $employee)
    {
        if ($employee->punches()->exists()) {
            $employee->update(['is_active' => false]);
            $employee->faceSamples()->delete();
            AuditLog::record('employee.deactivated', $employee);

            return back()->with('status', 'Employee has attendance records, so they were deactivated and their face data deleted instead.');
        }

        AuditLog::record('employee.deleted', $employee, ['employee_no' => $employee->employee_no]);
        DB::transaction(function () use ($employee) {
            $user = $employee->user;
            $employee->delete();
            $user?->delete();
        });

        return redirect()->route('admin.employees.index')->with('status', 'Employee deleted.');
    }

    private function validated(Request $request, ?Employee $employee = null): array
    {
        $time = ['nullable', 'date_format:H:i'];
        $userId = $employee?->user_id;

        $v = $request->validate([
            'employee_no' => ['required', 'string', 'max:50', Rule::unique('employees')->ignore($employee)],
            'last_name' => ['required', 'string', 'max:100'],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'position' => ['nullable', 'string', 'max:150'],
            'office' => ['nullable', 'string', 'max:150'],
            'supervisor_name' => ['nullable', 'string', 'max:150'],
            'am_in' => $time, 'am_out' => $time, 'pm_in' => $time, 'pm_out' => $time,
            'employment_type' => ['required', Rule::in(array_keys(config('hris.employment_types')))],
            'schedule_type' => ['required', 'in:flexi,fixed'],
            'punch_mode' => ['nullable', Rule::in(array_keys(Punch::MODES))],
            'supervisor_id' => ['nullable', 'exists:employees,id', Rule::notIn(array_filter([$employee?->id]))],
            'position_id' => ['nullable', 'exists:positions,id', Rule::unique('employees', 'position_id')->ignore($employee)],
            'salary_grade' => ['nullable', 'integer', 'min:1', 'max:33'],
            'step' => ['nullable', 'integer', 'min:1', 'max:8'],
            'monthly_salary' => ['nullable', 'numeric', 'min:0'],
            'daily_rate' => ['nullable', 'numeric', 'min:0'],
            'contract_start' => ['nullable', 'date'],
            'contract_end' => ['nullable', 'date', 'after_or_equal:contract_start'],
            'agency_name' => ['nullable', 'string', 'max:200'],
            'agency_contract_no' => ['nullable', 'string', 'max:100'],
            'sex' => ['nullable', 'in:male,female'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'civil_status' => ['nullable', 'in:single,married,widowed,separated,solo_parent'],
            'email' => ['nullable', 'email', 'max:150'],
            'mobile' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:250'],
            'tin' => ['nullable', 'string', 'max:30'], 'gsis_no' => ['nullable', 'string', 'max:30'], 'philhealth_no' => ['nullable', 'string', 'max:30'],
            'pagibig_no' => ['nullable', 'string', 'max:30'], 'sss_no' => ['nullable', 'string', 'max:30'],
            'bank_account_no' => ['nullable', 'string', 'max:40', 'regex:/^[0-9 -]+$/'],
            'date_hired' => ['nullable', 'date'],
            'step_date' => ['nullable', 'date'],
            'date_separated' => ['nullable', 'date'],
            'separation_reason' => ['nullable', 'string', 'max:200'],
            'emergency_contact' => ['nullable', 'string', 'max:150'],
            'emergency_phone' => ['nullable', 'string', 'max:30'],
            'username' => ['nullable', 'string', 'max:100', 'alpha_dash', Rule::unique('users', 'username')->ignore($userId)],
            'password' => [$request->filled('username') && ! $userId ? 'required' : 'nullable', Password::defaults()],
        ]);

        return [
            'employee' => collect($v)->only([
                'employee_no', 'last_name', 'first_name', 'middle_name', 'suffix', 'position', 'office',
                'supervisor_name', 'am_in', 'am_out', 'pm_in', 'pm_out', 'employment_type', 'schedule_type', 'punch_mode', 'supervisor_id',
                'position_id', 'salary_grade', 'step', 'monthly_salary', 'daily_rate', 'contract_start', 'contract_end', 'agency_name',
                'agency_contract_no', 'sex', 'birth_date', 'civil_status', 'email', 'mobile', 'address', 'tin', 'gsis_no', 'philhealth_no',
                'pagibig_no', 'sss_no', 'bank_account_no', 'date_hired', 'step_date', 'date_separated', 'separation_reason', 'emergency_contact', 'emergency_phone',
            ])->all() + [
                'is_active' => $request->boolean('is_active'),
                'allow_personal' => $request->boolean('allow_personal'),
            ],
            'account' => ['username' => $v['username'] ?? null, 'password' => $v['password'] ?? null],
        ];
    }

    /** Create, update, or remove the employee's login used for personal-device punching. */
    private function syncAccount(Employee $employee, array $account): void
    {
        $user = $employee->user;

        if (! $account['username']) {
            if ($user) {
                $employee->update(['user_id' => null]);
                $user->delete();
            }

            return;
        }

        // Keep the role of staff accounts (an HR officer is also an employee); new logins are employees.
        $attrs = ['name' => $employee->display_name, 'username' => $account['username']];
        if ($account['password']) {
            $attrs['password'] = $account['password'];
            $attrs['must_change_password'] = true; // HR set it, so the employee picks their own on first sign-in
        }

        if ($user) {
            $user->update($attrs);
        } else {
            $user = User::create([...$attrs, 'role' => 'employee']);
            $employee->update(['user_id' => $user->id]);
        }
    }
}
