<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pass slip / locator slip: leaving the office for a short time during work hours.
 * Official: the time out is excused on the DTR. Personal: the time out counts as undertime.
 */
class PassSlip extends Model
{
    public const TYPES = ['official' => 'Official (locator slip)', 'personal' => 'Personal'];

    protected $fillable = ['employee_id', 'date', 'type', 'time_out', 'time_back', 'destination', 'purpose', 'status', 'requested_by',
        'approved_by', 'approved_at', 'remarks', 'actual_out', 'actual_back'];

    protected function casts(): array
    {
        return ['date' => 'date', 'approved_at' => 'datetime'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function getTypeNameAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    /** Actual times when recorded, otherwise the planned ones. */
    public function outAt(): string
    {
        return $this->actual_out ?: $this->time_out;
    }

    public function backAt(): ?string
    {
        return $this->actual_back ?: $this->time_back;
    }

    public function returns(): bool
    {
        return $this->backAt() !== null;
    }

    /** Minutes out of the office, for a slip with a return time. */
    public function minutesOut(): int
    {
        if (! $this->returns()) {
            return 0;
        }
        $m = fn (string $t) => (int) substr($t, 0, 2) * 60 + (int) substr($t, 3, 2);

        return max(0, $m($this->backAt()) - $m($this->outAt()));
    }

    public function getTimesLabelAttribute(): string
    {
        $f = fn (?string $t) => $t ? CarbonImmutable::createFromFormat('H:i', $t)->format('g:i A') : null;

        return $f($this->outAt()).' – '.($this->returns() ? $f($this->backAt()) : 'not returning');
    }

    /** Personal pass slips the employee already used (approved) in that month. */
    public static function personalCount(Employee $employee, CarbonImmutable $month, ?int $except = null): int
    {
        return static::where('employee_id', $employee->id)->where('type', 'personal')->whereIn('status', ['approved', 'pending'])
            ->whereYear('date', $month->year)->whereMonth('date', $month->month)
            ->when($except, fn ($q) => $q->whereKeyNot($except))->count();
    }
}
