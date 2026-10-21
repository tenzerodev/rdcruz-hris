<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Official business, travel order, training or work-from-home: excuses the days on the DTR. */
class TravelOrder extends Model
{
    public const TYPES = ['ob' => 'Official Business', 'travel' => 'Travel Order', 'training' => 'Training / Seminar', 'wfh' => 'Work From Home'];

    public const SHORT = ['ob' => 'OB', 'travel' => 'TRAVEL', 'training' => 'TRAINING', 'wfh' => 'WFH'];

    protected $fillable = ['type', 'date_from', 'date_to', 'time_from', 'time_to', 'destination', 'purpose', 'reference_no', 'status',
        'requested_by', 'approved_by', 'approved_at', 'remarks'];

    protected function casts(): array
    {
        return ['date_from' => 'date', 'date_to' => 'date', 'approved_at' => 'datetime'];
    }

    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function getTypeNameAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    /** Whole-day orders excuse the day; an OB with hours only excuses that part. */
    public function isWholeDay(): bool
    {
        return ! $this->time_from || ! $this->time_to;
    }
}
