<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WfhArrangement extends Model
{
    public const DAYS = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat'];

    protected $fillable = ['employee_id', 'date_from', 'date_to', 'weekdays', 'require_device', 'reference_no', 'notes', 'cancelled_at', 'created_by'];

    protected function casts(): array
    {
        return [
            'date_from' => 'date',
            'date_to' => 'date',
            'weekdays' => 'array',
            'require_device' => 'boolean',
            'cancelled_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive(Builder $query): void
    {
        $query->whereNull('cancelled_at');
    }

    /** Does this arrangement make $date a WFH day? */
    public function covers(CarbonInterface $date): bool
    {
        return ! $this->cancelled_at
            && $date->betweenIncluded($this->date_from->copy()->startOfDay(), $this->date_to->copy()->endOfDay())
            && (empty($this->weekdays) || in_array($date->isoWeekday(), array_map('intval', $this->weekdays), true));
    }

    /** The arrangement that makes $date a WFH day for this employee, if any. */
    public static function forDay(Employee $employee, CarbonInterface $date): ?self
    {
        return static::active()->where('employee_id', $employee->id)
            ->whereDate('date_from', '<=', $date->toDateString())->whereDate('date_to', '>=', $date->toDateString())
            ->get()->first(fn (self $a) => $a->covers($date));
    }

    public function getDaysLabelAttribute(): string
    {
        if (empty($this->weekdays)) {
            return 'Every work day';
        }
        $days = array_map('intval', $this->weekdays);
        sort($days);

        return collect($days)->map(fn ($d) => self::DAYS[$d] ?? '?')->join(', ');
    }
}
