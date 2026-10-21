<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'employee_no', 'employment_type', 'schedule_type', 'punch_mode', 'last_name', 'first_name', 'middle_name', 'suffix',
        'position', 'position_id', 'office', 'supervisor_name', 'supervisor_id', 'salary_grade', 'step', 'monthly_salary', 'daily_rate',
        'contract_start', 'contract_end', 'agency_name', 'agency_contract_no', 'sex', 'birth_date', 'civil_status', 'email', 'mobile',
        'address', 'tin', 'gsis_no', 'philhealth_no', 'pagibig_no', 'sss_no', 'date_hired', 'date_separated', 'separation_reason',
        'emergency_contact', 'emergency_phone', 'step_date', 'bank_account_no', 'am_in', 'am_out', 'pm_in', 'pm_out', 'allow_personal', 'is_active', 'consent_at',
    ];

    protected function casts(): array
    {
        return [
            'allow_personal' => 'boolean',
            'is_active' => 'boolean',
            'consent_at' => 'datetime',
            'contract_start' => 'date',
            'contract_end' => 'date',
            'birth_date' => 'date',
            'date_hired' => 'date',
            'date_separated' => 'date',
            'monthly_salary' => 'decimal:2',
            'daily_rate' => 'decimal:2',
            'pds' => 'array',
            'pds_updated_at' => 'datetime',
            'face_purged_at' => 'datetime',
            'login_device_only' => 'boolean',
            'step_date' => 'date',
            'tin' => 'encrypted',
            'gsis_no' => 'encrypted',
            'philhealth_no' => 'encrypted',
            'pagibig_no' => 'encrypted',
            'sss_no' => 'encrypted',
            'bank_account_no' => 'encrypted',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function faceSamples(): HasMany
    {
        return $this->hasMany(FaceSample::class);
    }

    public function punches(): HasMany
    {
        return $this->hasMany(Punch::class);
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'supervisor_id');
    }

    public function subordinates(): HasMany
    {
        return $this->hasMany(Employee::class, 'supervisor_id');
    }

    public function plantillaPosition(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'position_id');
    }

    public function serviceRecords(): HasMany
    {
        return $this->hasMany(ServiceRecord::class)->orderBy('date_from');
    }

    public function educations(): HasMany
    {
        return $this->hasMany(Education::class);
    }

    public function eligibilities(): HasMany
    {
        return $this->hasMany(Eligibility::class);
    }

    public function trainings(): HasMany
    {
        return $this->hasMany(Training::class)->orderByDesc('date_from');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class)->latest();
    }

    public function leaveApplications(): HasMany
    {
        return $this->hasMany(LeaveApplication::class)->latest('date_from');
    }

    public function ledger(): HasMany
    {
        return $this->hasMany(LeaveLedger::class);
    }

    public function travelOrders(): BelongsToMany
    {
        return $this->belongsToMany(TravelOrder::class);
    }

    public function orgAssignments(): HasMany
    {
        return $this->hasMany(OrgAssignment::class);
    }

    /** Unit in the structure in effect today (interim if one is active), or null. */
    public function currentUnit(): ?OrgUnit
    {
        $structure = OrgStructure::current();

        return $structure ? $this->orgAssignments()->where('org_structure_id', $structure->id)->first()?->unit : null;
    }

    public function ipcrs(): HasMany
    {
        return $this->hasMany(Ipcr::class);
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class)->latest();
    }

    public function salaryNotices(): HasMany
    {
        return $this->hasMany(SalaryNotice::class)->orderByDesc('effective_date');
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }

    public function wfhArrangements(): HasMany
    {
        return $this->hasMany(WfhArrangement::class);
    }

    public function issuedDevices(): HasMany
    {
        return $this->hasMany(IssuedDevice::class);
    }

    public function overtimeAuthorizations(): HasMany
    {
        return $this->hasMany(OvertimeAuthorization::class);
    }

    /** Rules for this person's employment type (config/hris.php). */
    public function rules(): array
    {
        return config('hris.employment_types.'.$this->employment_type) ?? config('hris.employment_types.permanent');
    }

    public function earnsLeave(): bool
    {
        return (bool) $this->rules()['leave_credits'];
    }

    public function overtimeEligible(): bool
    {
        return (bool) $this->rules()['overtime'];
    }

    public function getTypeLabelAttribute(): string
    {
        return $this->rules()['label'];
    }

    public function getGroupAttribute(): string
    {
        return $this->rules()['group'];
    }

    public function contractEndsSoon(): bool
    {
        return $this->contract_end !== null && $this->contract_end->lte(today()->addDays(config('hris.contract_warning_days')));
    }

    /** Effective "In Charge" name for the DTR. */
    public function getVerifierNameAttribute(): ?string
    {
        return $this->supervisor_name ?: $this->supervisor?->display_name;
    }

    /** "DELA CRUZ, Juan M. Jr." */
    public function getFullNameAttribute(): string
    {
        $mi = $this->middle_name ? ' '.mb_substr($this->middle_name, 0, 1).'.' : '';
        $suffix = $this->suffix ? ' '.$this->suffix : '';

        return mb_strtoupper($this->last_name).', '.$this->first_name.$mi.$suffix;
    }

    /** "Juan M. Dela Cruz Jr." for the Form 48 name line. */
    public function getDisplayNameAttribute(): string
    {
        $mi = $this->middle_name ? ' '.mb_substr($this->middle_name, 0, 1).'.' : '';
        $suffix = $this->suffix ? ' '.$this->suffix : '';

        return $this->first_name.$mi.' '.$this->last_name.$suffix;
    }

    /** Official hours for this employee, falling back to global settings. */
    public function officialHours(): array
    {
        return [
            'am_in' => $this->am_in ?: Setting::get('am_in'),
            'am_out' => $this->am_out ?: Setting::get('am_out'),
            'pm_in' => $this->pm_in ?: Setting::get('pm_in'),
            'pm_out' => $this->pm_out ?: Setting::get('pm_out'),
        ];
    }

    /** 'two' = one time in and one time out a day; 'four' = AM and PM in/out. Falls back to the agency default. */
    public function punchMode(): string
    {
        return $this->punch_mode ?: (Setting::get('punch_mode') === 'two' ? 'two' : 'four');
    }

    public function punchesOncePerDay(): bool
    {
        return $this->punchMode() === 'two';
    }

    /** Label for a punch type as this employee sees it ("Time In" instead of "AM Arrival" on once-a-day mode). */
    public function punchLabel(string $type): string
    {
        return Punch::labelFor($type, $this->punchesOncePerDay());
    }

    public function canRecognize(): bool
    {
        return $this->is_active && $this->consent_at !== null;
    }
}
