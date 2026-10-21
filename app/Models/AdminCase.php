<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** An administrative case against an employee (2017 RACCS process). Confidential to HR. */
class AdminCase extends Model
{
    public const STATUSES = [
        'complaint' => 'Complaint received', 'preliminary' => 'Preliminary investigation', 'formal_charge' => 'Formal charge issued',
        'hearing' => 'Formal investigation / hearing', 'for_decision' => 'For decision', 'decided' => 'Decided', 'appealed' => 'On appeal',
        'dismissed' => 'Dismissed (no prima facie case)', 'closed' => 'Closed',
    ];

    public const CLASSIFICATIONS = ['grave' => 'Grave', 'less_grave' => 'Less grave', 'light' => 'Light'];

    public const PENALTIES = [
        'exonerated' => 'Exonerated', 'reprimand' => 'Reprimand', 'fine' => 'Fine', 'suspension' => 'Suspension',
        'demotion' => 'Demotion', 'dismissal' => 'Dismissal from the service',
    ];

    protected $fillable = ['case_no', 'employee_id', 'complainant', 'offense', 'classification', 'description', 'date_filed', 'status',
        'penalty', 'decision', 'decided_on', 'closed_on', 'created_by'];

    protected function casts(): array
    {
        return ['date_filed' => 'date', 'decided_on' => 'date', 'closed_on' => 'date'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(CaseEvent::class)->orderBy('date')->orderBy('id');
    }

    public function suspensions(): HasMany
    {
        return $this->hasMany(CaseSuspension::class)->orderBy('date_from');
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function isOpen(): bool
    {
        return ! in_array($this->status, ['decided', 'dismissed', 'closed'], true);
    }
}
