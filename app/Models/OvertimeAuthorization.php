<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OvertimeAuthorization extends Model
{
    protected $fillable = ['employee_id', 'date_from', 'date_to', 'max_minutes_per_day', 'purpose', 'reference_no', 'status',
        'requested_by', 'approved_by', 'decided_at', 'remarks'];

    protected function casts(): array
    {
        return ['date_from' => 'date', 'date_to' => 'date', 'max_minutes_per_day' => 'integer', 'decided_at' => 'datetime'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
