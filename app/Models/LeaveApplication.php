<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveApplication extends Model
{
    public const STATUSES = ['pending' => 'Pending', 'recommended' => 'Recommended', 'approved' => 'Approved', 'disapproved' => 'Disapproved', 'cancelled' => 'Cancelled'];

    protected $fillable = ['employee_id', 'type', 'date_from', 'date_to', 'half_day', 'days', 'details', 'commutation', 'reason', 'status',
        'filed_by', 'recommended_by', 'recommended_at', 'approved_by', 'approved_at', 'days_with_pay', 'days_without_pay', 'remarks'];

    protected function casts(): array
    {
        return [
            'date_from' => 'date', 'date_to' => 'date', 'details' => 'array', 'commutation' => 'boolean',
            'recommended_at' => 'datetime', 'approved_at' => 'datetime',
            'days' => 'float', 'days_with_pay' => 'float', 'days_without_pay' => 'float',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function filer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'filed_by');
    }

    public function recommender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recommended_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function getTypeNameAttribute(): string
    {
        return config("hris.leave_types.{$this->type}.name", $this->type);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['pending', 'recommended'], true);
    }

    public function covers(string $date): bool
    {
        return $date >= $this->date_from->toDateString() && $date <= $this->date_to->toDateString();
    }
}
