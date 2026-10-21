<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Punch extends Model
{
    public const TYPES = ['am_in', 'am_out', 'pm_in', 'pm_out'];

    public const LABELS = [
        'am_in' => 'AM Arrival',
        'am_out' => 'AM Departure',
        'pm_in' => 'PM Arrival',
        'pm_out' => 'PM Departure',
    ];

    /** Labels for employees who punch once a day (one time in, one time out). */
    public const ONCE_A_DAY_LABELS = [
        'am_in' => 'Time In',
        'am_out' => 'Time Out',
        'pm_in' => 'Time In',
        'pm_out' => 'Time Out',
    ];

    public const ARRIVALS = ['am_in', 'pm_in'];

    public const DEPARTURES = ['am_out', 'pm_out'];

    public const MODES = [
        'four' => 'Morning and afternoon (AM in/out, PM in/out)',
        'two' => 'Once a day (one time in, one time out)',
    ];

    protected $fillable = [
        'employee_id', 'work_date', 'type', 'punched_at', 'source', 'kiosk_id', 'issued_device_id', 'distance', 'liveness',
        'latitude', 'longitude', 'accuracy', 'snapshot_path', 'ip', 'user_agent', 'created_by', 'remarks',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'punched_at' => 'datetime',
            'liveness' => 'boolean',
            'distance' => 'float',
            'accuracy' => 'float',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(IssuedDevice::class, 'issued_device_id');
    }

    public function kiosk(): BelongsTo
    {
        return $this->belongsTo(Kiosk::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function labelFor(string $type, bool $onceADay = false): string
    {
        return ($onceADay ? self::ONCE_A_DAY_LABELS : self::LABELS)[$type] ?? $type;
    }

    public function getLabelAttribute(): string
    {
        $onceADay = $this->relationLoaded('employee') && $this->employee?->punchesOncePerDay();

        return self::labelFor($this->type, (bool) $onceADay);
    }
}
