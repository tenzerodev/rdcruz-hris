<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveMonetization extends Model
{
    public const KINDS = ['regular' => 'Monetization of leave credits', 'special' => 'Special monetization (50% or more)', 'terminal' => 'Terminal leave benefit'];

    protected $fillable = ['employee_id', 'kind', 'date', 'vl_days', 'sl_days', 'salary', 'factor', 'amount', 'reason', 'created_by'];

    protected function casts(): array
    {
        return ['date' => 'date', 'vl_days' => 'float', 'sl_days' => 'float', 'salary' => 'float', 'factor' => 'float', 'amount' => 'float'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getKindNameAttribute(): string
    {
        return self::KINDS[$this->kind] ?? $this->kind;
    }
}
