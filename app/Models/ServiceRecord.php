<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceRecord extends Model
{
    protected $fillable = ['employee_id', 'date_from', 'date_to', 'designation', 'status', 'annual_salary', 'office', 'branch', 'leave_without_pay', 'separation', 'remarks'];

    protected function casts(): array
    {
        return ['date_from' => 'date', 'date_to' => 'date', 'annual_salary' => 'decimal:2'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
