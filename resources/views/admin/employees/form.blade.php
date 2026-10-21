@extends('layouts.app')
@section('title', $employee->exists ? 'Edit employee' : 'Add employee')
@section('content')
@php
    $types = config('hris.employment_types');
    $v = fn ($f) => old($f, $employee->$f instanceof \Carbon\CarbonInterface ? $employee->$f->toDateString() : $employee->$f);
@endphp
<div class="page-head">
    <h1>{{ $employee->exists ? 'Edit '.$employee->full_name : 'Add employee' }}</h1>
    @if ($employee->exists)
        <div class="actions">
            <a class="btn" href="{{ route('admin.employees.show', $employee) }}">View profile</a>
            <a class="btn" href="{{ route('admin.employees.enroll', $employee) }}">Enroll face ({{ $employee->face_samples_count }})</a>
        </div>
    @endif
</div>
<form method="post" action="{{ $employee->exists ? route('admin.employees.update', $employee) : route('admin.employees.store') }}" class="grid grid-2" id="employee-form">
    @csrf @if ($employee->exists) @method('put') @endif
    <div class="stack">
        <section class="card">
            <h2>Employment</h2>
            <div class="row">
                <div class="field"><label for="employee_no">Employee / ID no.</label><input type="text" id="employee_no" name="employee_no" value="{{ $v('employee_no') }}" required></div>
                <div class="field"><label for="employment_type">Employment type</label>
                    <select id="employment_type" name="employment_type" required>
                        @foreach (config('hris.groups') as $group => $groupLabel)
                            <optgroup label="{{ $groupLabel }}">
                                @foreach ($types as $key => $t)
                                    @if ($t['group'] === $group)<option value="{{ $key }}" data-rules='@json($t)' @selected($v('employment_type') === $key)>{{ $t['label'] }}</option>@endif
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                    <div class="hint" id="type-rules"></div>
                </div>
            </div>
            <div class="field" data-show="plantilla"><label for="position_id">Plantilla item</label>
                <select id="position_id" name="position_id"><option value="">— None —</option>
                    @foreach ($positions as $p)<option value="{{ $p->id }}" @selected($v('position_id') == $p->id)>{{ $p->item_no }} · {{ $p->title }}{{ $p->salary_grade ? ' (SG '.$p->salary_grade.')' : '' }}</option>@endforeach
                </select></div>
            <div class="row">
                <div class="field"><label for="position">Position / designation</label><input type="text" id="position" name="position" value="{{ $v('position') }}"></div>
                <div class="field"><label for="office">Office / division</label><input type="text" id="office" name="office" value="{{ $v('office') }}"></div>
            </div>
            <div class="row" data-show="plantilla-pay">
                <div class="field"><label for="salary_grade">Salary grade</label><input type="number" id="salary_grade" name="salary_grade" min="1" max="33" value="{{ $v('salary_grade') }}"></div>
                <div class="field"><label for="step">Step</label><input type="number" id="step" name="step" min="1" max="8" value="{{ $v('step') }}"></div>
                <div class="field"><label for="monthly_salary">Monthly salary</label><input type="number" id="monthly_salary" name="monthly_salary" step="0.01" min="0" value="{{ $v('monthly_salary') }}"></div>
            </div>
            <div class="row" data-show="contract">
                <div class="field"><label for="contract_start">Contract start</label><input type="date" id="contract_start" name="contract_start" value="{{ $v('contract_start') }}"></div>
                <div class="field"><label for="contract_end">Contract end</label><input type="date" id="contract_end" name="contract_end" value="{{ $v('contract_end') }}"></div>
                <div class="field"><label for="daily_rate">Daily / contract rate</label><input type="number" id="daily_rate" name="daily_rate" step="0.01" min="0" value="{{ $v('daily_rate') }}"></div>
            </div>
            <div class="row" data-show="agency">
                <div class="field"><label for="agency_name" id="agency-label">Agency / company</label><input type="text" id="agency_name" name="agency_name" value="{{ $v('agency_name') }}"></div>
                <div class="field"><label for="agency_contract_no">Contract / detail order no.</label><input type="text" id="agency_contract_no" name="agency_contract_no" value="{{ $v('agency_contract_no') }}"></div>
            </div>
            <div class="row">
                <div class="field"><label for="date_hired">Date hired / first engaged</label><input type="date" id="date_hired" name="date_hired" value="{{ $v('date_hired') }}"><div class="hint">First day in the agency (not a contract renewal). Used for loyalty awards, bonuses and wellness leave.</div></div>
                <div class="field"><label for="step_date">Step date</label><input type="date" id="step_date" name="step_date" value="{{ $v('step_date') }}"><div class="hint">When the current step took effect (appointment or last increment). Blank = date hired.</div></div>
                <div class="field"><label for="supervisor_id">Immediate supervisor</label>
                    <select id="supervisor_id" name="supervisor_id"><option value="">— None —</option>
                        @foreach ($supervisors as $s)<option value="{{ $s->id }}" @selected($v('supervisor_id') == $s->id)>{{ $s->full_name }}</option>@endforeach
                    </select><div class="hint">Approves leave, overtime and travel requests.</div></div>
            </div>
            <label class="check"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $employee->is_active))> Active</label>
            @if ($employee->exists)
            <div class="row" style="margin-top:12px">
                <div class="field"><label for="date_separated">Date separated</label><input type="date" id="date_separated" name="date_separated" value="{{ $v('date_separated') }}"></div>
                <div class="field"><label for="separation_reason">Reason</label><input type="text" id="separation_reason" name="separation_reason" value="{{ $v('separation_reason') }}" placeholder="Resignation, retirement, end of contract…"></div>
            </div>
            @endif
        </section>

        <section class="card">
            <h2>Schedule</h2>
            <div class="field"><label for="schedule_type">Work schedule</label>
                <select id="schedule_type" name="schedule_type">
                    <option value="flexi" @selected($v('schedule_type') === 'flexi')>Flexi-time (arrive {{ \App\Models\Setting::get('flexi_start') }}–{{ \App\Models\Setting::get('flexi_end') }}, {{ (int) \App\Models\Setting::get('required_minutes') / 60 }} hrs)</option>
                    <option value="fixed" @selected($v('schedule_type') === 'fixed')>Fixed hours</option>
                </select></div>
            <div class="field"><label for="punch_mode">Time in / time out</label>
                <select id="punch_mode" name="punch_mode">
                    <option value="">Agency default ({{ \App\Models\Punch::MODES[\App\Models\Setting::get('punch_mode') === 'two' ? 'two' : 'four'] }})</option>
                    @foreach (\App\Models\Punch::MODES as $k => $l)<option value="{{ $k }}" @selected($v('punch_mode') === $k)>{{ $l }}</option>@endforeach
                </select><div class="hint">Once a day: lunch is not punched; it is deducted automatically. Works with both flexi-time and fixed hours.</div></div>
            <div class="row" data-show-schedule="fixed">
                @foreach (['am_in' => 'AM arrival', 'am_out' => 'AM departure', 'pm_in' => 'PM arrival', 'pm_out' => 'PM departure'] as $f => $l)
                    <div class="field"><label for="{{ $f }}">{{ $l }}</label><input type="time" id="{{ $f }}" name="{{ $f }}" value="{{ old($f, $employee->$f) }}"></div>
                @endforeach
            </div>
            <p class="hint" data-show-schedule="fixed">Blank uses the office hours in Settings.</p>
            <div class="field"><label for="supervisor_name">"In Charge" on Form 48 (optional)</label><input type="text" id="supervisor_name" name="supervisor_name" value="{{ $v('supervisor_name') }}"><div class="hint">Blank uses the immediate supervisor's name.</div></div>
        </section>
    </div>

    <div class="stack">
        <section class="card">
            <h2>Personal information</h2>
            <div class="row">
                <div class="field"><label for="last_name">Last name</label><input type="text" id="last_name" name="last_name" value="{{ $v('last_name') }}" required></div>
                <div class="field"><label for="first_name">First name</label><input type="text" id="first_name" name="first_name" value="{{ $v('first_name') }}" required></div>
            </div>
            <div class="row">
                <div class="field"><label for="middle_name">Middle name</label><input type="text" id="middle_name" name="middle_name" value="{{ $v('middle_name') }}"></div>
                <div class="field"><label for="suffix">Suffix</label><input type="text" id="suffix" name="suffix" value="{{ $v('suffix') }}"></div>
            </div>
            <div class="row">
                <div class="field"><label for="sex">Sex</label><select id="sex" name="sex"><option value=""></option><option value="male" @selected($v('sex') === 'male')>Male</option><option value="female" @selected($v('sex') === 'female')>Female</option></select></div>
                <div class="field"><label for="birth_date">Birth date</label><input type="date" id="birth_date" name="birth_date" value="{{ $v('birth_date') }}"></div>
                <div class="field"><label for="civil_status">Civil status</label><select id="civil_status" name="civil_status"><option value=""></option>
                    @foreach (['single' => 'Single', 'married' => 'Married', 'widowed' => 'Widowed', 'separated' => 'Separated', 'solo_parent' => 'Solo parent'] as $k => $l)<option value="{{ $k }}" @selected($v('civil_status') === $k)>{{ $l }}</option>@endforeach
                </select></div>
            </div>
            <div class="row">
                <div class="field"><label for="email">Email</label><input type="text" id="email" name="email" inputmode="email" value="{{ $v('email') }}"></div>
                <div class="field"><label for="mobile">Mobile</label><input type="text" id="mobile" name="mobile" inputmode="tel" value="{{ $v('mobile') }}"></div>
            </div>
            <div class="field"><label for="address">Address</label><input type="text" id="address" name="address" value="{{ $v('address') }}"></div>
            <div class="row">
                <div class="field"><label for="emergency_contact">Emergency contact</label><input type="text" id="emergency_contact" name="emergency_contact" value="{{ $v('emergency_contact') }}"></div>
                <div class="field"><label for="emergency_phone">Their phone</label><input type="text" id="emergency_phone" name="emergency_phone" value="{{ $v('emergency_phone') }}"></div>
            </div>
        </section>
        <section class="card">
            <h2>Government numbers</h2>
            <div class="row">
                @foreach (['tin' => 'TIN', 'gsis_no' => 'GSIS BP no.', 'philhealth_no' => 'PhilHealth', 'pagibig_no' => 'Pag-IBIG MID', 'sss_no' => 'SSS (COS/JO)', 'bank_account_no' => 'Payroll bank account no.'] as $f => $l)
                    <div class="field"><label for="{{ $f }}">{{ $l }}</label><input type="text" id="{{ $f }}" name="{{ $f }}" value="{{ $v($f) }}"></div>
                @endforeach
            </div>
        </section>
        <section class="card">
            <h2>Login for own phone / laptop</h2>
            <p class="hint">Needed to file leave, overtime and travel requests and to time in from their own device. Leave blank for kiosk-only.</p>
            <div class="row">
                <div class="field"><label for="username">Username</label><input type="text" id="username" name="username" value="{{ old('username', $employee->user?->username) }}" autocomplete="off"></div>
                <div class="field"><label for="password">{{ $employee->user ? 'New password (blank = keep)' : 'Password' }}</label><input type="password" id="password" name="password" autocomplete="new-password"></div>
            </div>
            <label class="check"><input type="checkbox" name="allow_personal" value="1" @checked(old('allow_personal', $employee->allow_personal))> Allow time in/out from own device</label>
        </section>
        <div class="actions" style="justify-content:flex-start">
            <button class="btn btn-primary">Save</button>
            <a class="btn" href="{{ $employee->exists ? route('admin.employees.show', $employee) : route('admin.employees.index') }}">Cancel</a>
        </div>
    </div>
</form>
@if ($employee->exists)
<form method="post" action="{{ route('admin.employees.destroy', $employee) }}" style="margin-top:24px" onsubmit="return confirm('Delete this employee? If they have records they will be deactivated instead.')">
    @csrf @method('delete')
    <button class="btn btn-danger btn-sm">Delete employee</button>
</form>
@endif
@endsection
@push('scripts')
<script>
(() => {
  const type = document.getElementById('employment_type');
  const sched = document.getElementById('schedule_type');
  const show = (sel, on) => document.querySelectorAll(sel).forEach((el) => { el.hidden = !on; });
  function update() {
    const r = JSON.parse(type.selectedOptions[0].dataset.rules);
    show('[data-show="plantilla"]', r.plantilla);
    show('[data-show="plantilla-pay"]', r.group === 'plantilla');
    show('[data-show="contract"]', r.contract);
    show('[data-show="agency"]', r.agency);
    document.getElementById('agency-label').textContent = type.value === 'dbp' ? 'DBP office / unit' : 'Agency / company';
    document.getElementById('type-rules').textContent = [
      r.leave_credits ? 'earns leave credits' : 'no leave credits',
      r.overtime ? 'can claim overtime/COC' : 'no overtime claims',
    ].join(' · ');
    show('[data-show-schedule="fixed"]', sched.value === 'fixed');
  }
  type.addEventListener('change', update); sched.addEventListener('change', update); update();
})();
</script>
@endpush
