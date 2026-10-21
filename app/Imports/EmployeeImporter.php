<?php

namespace App\Imports;

use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use Illuminate\Validation\Rule;

class EmployeeImporter extends Importer
{
    public function key(): string
    {
        return 'employees';
    }

    public function title(): string
    {
        return 'Employees';
    }

    public function description(): string
    {
        return 'Add or update personnel of every type — plantilla, COS/JO, service providers and DBP personnel. Rows are matched by employee number: existing employees are updated, new ones are added.';
    }

    public function sectionRoute(): string
    {
        return 'admin.employees.index';
    }

    public function columns(): array
    {
        $types = implode(' | ', array_keys(config('hris.employment_types')));

        return [
            ['name' => 'employee_no', 'label' => 'Employee no.', 'required' => true, 'format' => 'Text, unique', 'example' => '2019-014', 'rules' => ['string', 'max:50']],
            ['name' => 'last_name', 'label' => 'Last name', 'required' => true, 'format' => 'Text', 'example' => 'Dela Cruz', 'rules' => ['string', 'max:100']],
            ['name' => 'first_name', 'label' => 'First name', 'required' => true, 'format' => 'Text', 'example' => 'Juan', 'rules' => ['string', 'max:100']],
            ['name' => 'middle_name', 'label' => 'Middle name', 'format' => 'Text', 'example' => 'Santos', 'rules' => ['string', 'max:100']],
            ['name' => 'suffix', 'label' => 'Suffix', 'format' => 'Text (Jr., III)', 'example' => '', 'rules' => ['string', 'max:20']],
            ['name' => 'employment_type', 'label' => 'Employment type', 'required' => true, 'format' => "One of: $types", 'example' => 'permanent', 'rules' => [Rule::in(array_keys(config('hris.employment_types')))]],
            ['name' => 'position', 'label' => 'Position / designation', 'format' => 'Text', 'example' => 'Administrative Officer V', 'rules' => ['string', 'max:150']],
            ['name' => 'office', 'label' => 'Office / division', 'format' => 'Text', 'example' => 'Administrative Division', 'rules' => ['string', 'max:150']],
            ['name' => 'plantilla_item_no', 'label' => 'Plantilla item no.', 'format' => 'Text — must exist in Plantilla (import that first)', 'example' => 'ITEM-AO5-1', 'rules' => ['string']],
            ['name' => 'salary_grade', 'label' => 'Salary grade', 'format' => 'Number 1–33', 'example' => '18', 'rules' => ['integer', 'between:1,33']],
            ['name' => 'step', 'label' => 'Step', 'format' => 'Number 1–8', 'example' => '2', 'rules' => ['integer', 'between:1,8']],
            ['name' => 'monthly_salary', 'label' => 'Monthly salary', 'format' => 'Number, no ₱', 'example' => '52000', 'rules' => ['numeric', 'min:0']],
            ['name' => 'daily_rate', 'label' => 'Daily rate (COS/JO)', 'format' => 'Number', 'example' => '', 'rules' => ['numeric', 'min:0']],
            ['name' => 'contract_start', 'label' => 'Contract start', 'format' => 'Date (COS, JO, service providers, casual, contractual)', 'example' => '', 'rules' => ['date']],
            ['name' => 'contract_end', 'label' => 'Contract end', 'format' => 'Date', 'example' => '', 'rules' => ['date']],
            ['name' => 'agency_name', 'label' => 'Agency / company', 'format' => 'Text (service provider company or DBP office)', 'example' => '', 'rules' => ['string', 'max:200']],
            ['name' => 'supervisor_employee_no', 'label' => 'Supervisor employee no.', 'format' => 'Text — another employee in the system or in this file', 'example' => '2015-001', 'rules' => ['string']],
            ['name' => 'schedule_type', 'label' => 'Schedule', 'format' => 'flexi | fixed (blank = flexi)', 'example' => 'flexi', 'rules' => ['in:flexi,fixed']],
            ['name' => 'sex', 'label' => 'Sex', 'format' => 'male | female', 'example' => 'male', 'rules' => ['in:male,female']],
            ['name' => 'birth_date', 'label' => 'Birth date', 'format' => 'Date', 'example' => '1990-05-14', 'rules' => ['date', 'before:today']],
            ['name' => 'civil_status', 'label' => 'Civil status', 'format' => 'single | married | widowed | separated | solo_parent', 'example' => 'married', 'rules' => ['in:single,married,widowed,separated,solo_parent']],
            ['name' => 'email', 'label' => 'Email', 'format' => 'Email address (used for notifications)', 'example' => 'juan.delacruz@agency.gov.ph', 'rules' => ['email', 'max:150']],
            ['name' => 'mobile', 'label' => 'Mobile', 'format' => 'Text', 'example' => '09171234567', 'rules' => ['string', 'max:30']],
            ['name' => 'tin', 'label' => 'TIN', 'format' => 'Text', 'example' => '123-456-789-000', 'rules' => ['string', 'max:30']],
            ['name' => 'gsis_no', 'label' => 'GSIS BP no.', 'format' => 'Text', 'example' => '', 'rules' => ['string', 'max:30']],
            ['name' => 'philhealth_no', 'label' => 'PhilHealth no.', 'format' => 'Text', 'example' => '', 'rules' => ['string', 'max:30']],
            ['name' => 'pagibig_no', 'label' => 'Pag-IBIG MID', 'format' => 'Text', 'example' => '', 'rules' => ['string', 'max:30']],
            ['name' => 'sss_no', 'label' => 'SSS no.', 'format' => 'Text', 'example' => '', 'rules' => ['string', 'max:30']],
            ['name' => 'date_hired', 'label' => 'Date hired', 'format' => 'Date', 'example' => '2019-06-03', 'rules' => ['date']],
            ['name' => 'step_date', 'label' => 'Step date', 'format' => 'Date (when the current step took effect)', 'example' => '2022-06-03', 'rules' => ['date']],
            ['name' => 'username', 'label' => 'Login username', 'format' => 'Letters, numbers, - and _ (blank = kiosk only)', 'example' => 'jdelacruz', 'rules' => ['alpha_dash', 'max:100']],
            ['name' => 'password', 'label' => 'Initial password', 'format' => 'At least 8 characters; only used when the login is new', 'example' => 'ChangeMe2026', 'rules' => ['string', 'min:8']],
        ];
    }

    public function moreExamples(): array
    {
        $blank = array_fill_keys($this->headers(), '');

        return [
            array_values(array_merge($blank, ['employee_no' => 'COS-2026-07', 'last_name' => 'Garcia', 'first_name' => 'Mika', 'employment_type' => 'cos', 'position' => 'Graphic Artist',
                'office' => 'Marketing', 'daily_rate' => '1500', 'contract_start' => '2026-01-02', 'contract_end' => '2026-12-31', 'supervisor_employee_no' => '2015-001', 'username' => 'mgarcia', 'password' => 'ChangeMe2026'])),
            array_values(array_merge($blank, ['employee_no' => 'SP-0042', 'last_name' => 'Ramos', 'first_name' => 'Ben', 'employment_type' => 'service_provider', 'position' => 'Security Guard',
                'office' => 'General Services', 'agency_name' => 'Sentinel Security Agency', 'contract_start' => '2026-01-01', 'contract_end' => '2026-12-31'])),
            array_values(array_merge($blank, ['employee_no' => 'DBP-118', 'last_name' => 'Torres', 'first_name' => 'Lito', 'employment_type' => 'dbp', 'position' => 'Accountant III',
                'office' => 'Finance', 'agency_name' => 'DBP Head Office'])),
        ];
    }

    public function notes(): array
    {
        return [
            'Import Plantilla items first if you fill in plantilla_item_no.',
            'Supervisors can be in the same file — they are linked after all rows are saved.',
            'Updating an employee only changes the columns you fill in; blank cells keep the current value.',
            'Passwords are only used when a login is created. Ask staff to change them after the first sign-in.',
        ];
    }

    protected function check(array $row): array
    {
        $errors = [];
        if ($row['plantilla_item_no'] && ! Position::where('item_no', $row['plantilla_item_no'])->exists()) {
            $errors[] = "Plantilla item “{$row['plantilla_item_no']}” is not in the Plantilla yet.";
        }
        if ($row['username']) {
            $taken = User::where('username', $row['username'])->first();
            $mine = $this->employee($row['employee_no'])?->user_id;
            if ($taken && $taken->id !== $mine) {
                $errors[] = "Username “{$row['username']}” is already used.";
            }
            if (! $mine && ! $row['password']) {
                $errors[] = 'Give an initial password for the new login.';
            }
        }
        if ($row['contract_start'] && $row['contract_end'] && $row['contract_end'] < $row['contract_start']) {
            $errors[] = 'Contract end is before contract start.';
        }

        return $errors;
    }

    public function save(array $row): string
    {
        $existing = $this->employee($row['employee_no']);
        $fields = collect($row)->only(['last_name', 'first_name', 'middle_name', 'suffix', 'employment_type', 'position', 'office', 'salary_grade', 'step',
            'monthly_salary', 'daily_rate', 'contract_start', 'contract_end', 'agency_name', 'schedule_type', 'sex', 'birth_date', 'civil_status', 'email',
            'mobile', 'tin', 'gsis_no', 'philhealth_no', 'pagibig_no', 'sss_no', 'date_hired', 'step_date'])->filter(fn ($v) => $v !== null)->all();
        if ($row['plantilla_item_no']) {
            $fields['position_id'] = Position::where('item_no', $row['plantilla_item_no'])->value('id');
        }

        $employee = $existing ?? new Employee(['employee_no' => $row['employee_no'], 'is_active' => true, 'allow_personal' => true, 'schedule_type' => 'flexi']);
        $employee->fill($fields)->save();
        $this->forgetEmployee($row['employee_no']);

        if ($row['username']) {
            if ($employee->user) {
                $employee->user->update(['username' => $row['username'], 'name' => $employee->display_name]);
            } else {
                $user = User::create(['name' => $employee->display_name, 'username' => $row['username'], 'password' => $row['password'], 'role' => 'employee', 'must_change_password' => true]);
                $employee->update(['user_id' => $user->id]);
            }
        }

        return $existing ? 'updated' : 'created';
    }

    public function finish(array $rows): void
    {
        foreach ($rows as $row) {
            if ($row['supervisor_employee_no'] && $row['supervisor_employee_no'] !== $row['employee_no']) {
                $boss = Employee::where('employee_no', $row['supervisor_employee_no'])->value('id');
                Employee::where('employee_no', $row['employee_no'])->update(['supervisor_id' => $boss]);
            }
        }
    }

    /** A supervisor can be another row in this file or someone already in the system. */
    public function fileChecks(array $rows): array
    {
        $inFile = array_column($rows, 'employee_no');
        $errors = [];
        foreach ($rows as $row) {
            $no = $row['supervisor_employee_no'];
            if ($no && ! in_array($no, $inFile, true) && ! Employee::where('employee_no', $no)->exists()) {
                $errors[$row['_line']][] = "Supervisor “{$no}” is not in this file or in the system.";
            }
        }

        return $errors;
    }
}
