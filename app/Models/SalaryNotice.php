<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Notice of Step Increment (NOSI) or Notice of Salary Adjustment (NOSA). */
class SalaryNotice extends Model
{
    public const TYPES = ['nosi' => 'Notice of Step Increment', 'nosa' => 'Notice of Salary Adjustment'];

    protected $fillable = ['employee_id', 'type', 'effective_date', 'old_grade', 'old_step', 'old_salary', 'new_grade', 'new_step', 'new_salary', 'basis', 'salary_schedule_id', 'created_by'];

    protected function casts(): array
    {
        return ['effective_date' => 'date', 'old_salary' => 'float', 'new_salary' => 'float'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(SalarySchedule::class, 'salary_schedule_id');
    }

    public function getTypeNameAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }
}
