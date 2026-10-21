<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Days the employee is suspended (preventive or as a penalty): no absence on the DTR, no pay unless with pay. */
class CaseSuspension extends Model
{
    public const KINDS = ['preventive' => 'Preventive suspension', 'penalty' => 'Suspension (penalty)'];

    protected $fillable = ['admin_case_id', 'employee_id', 'kind', 'date_from', 'date_to', 'with_pay'];

    protected function casts(): array
    {
        return ['date_from' => 'date', 'date_to' => 'date', 'with_pay' => 'boolean'];
    }

    public function adminCase(): BelongsTo
    {
        return $this->belongsTo(AdminCase::class);
    }

    public function covers(string $date): bool
    {
        return $date >= $this->date_from->toDateString() && $date <= $this->date_to->toDateString();
    }
}
