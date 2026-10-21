<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentVerification extends Model
{
    public const TYPES = [
        'form48' => 'Daily Time Record (CSC Form 48)', 'form6' => 'Application for Leave (CSC Form 6)', 'service_record' => 'Service Record',
        'coe' => 'Certificate of Employment', 'travel_order' => 'Travel Order / Official Business', 'ipcr' => 'IPCR', 'pds' => 'Personal Data Sheet (CS Form 212)',
        'overtime_report' => 'Overtime Report', 'org_chart' => 'Organizational Chart', 'wfh_report' => 'Work-from-Home Accomplishment Report', 'payslip' => 'Payslip', 'nosi' => 'Notice of Step Increment', 'nosa' => 'Notice of Salary Adjustment', 'clearance' => 'Clearance Certificate', 'tax_2316' => 'Certificate of Compensation and Tax Withheld', 'monetization' => 'Leave Monetization / Terminal Leave Computation',
    ];

    protected $fillable = ['code', 'type', 'subject_key', 'employee_id', 'title', 'summary', 'fingerprint', 'issued_by', 'revoked_at', 'revoked_reason', 'views', 'last_viewed_at'];

    protected function casts(): array
    {
        return ['summary' => 'array', 'revoked_at' => 'datetime', 'last_viewed_at' => 'datetime'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function getTypeNameAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    /** "ABCD-EFGH-JKLM" for printing. */
    public function getDisplayCodeAttribute(): string
    {
        return implode('-', str_split($this->code, 4));
    }

    /** Always the public address (APP_URL), so a QR printed from any computer works for outsiders. */
    public function url(): string
    {
        return rtrim(config('app.url'), '/').'/verify/'.$this->code;
    }

    /** A later version of the same document exists. */
    public function newer(): ?self
    {
        return static::where('subject_key', $this->subject_key)->where('id', '>', $this->id)->whereNull('revoked_at')->latest('id')->first();
    }
}
