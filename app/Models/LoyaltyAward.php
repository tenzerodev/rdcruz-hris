<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoyaltyAward extends Model
{
    protected $fillable = ['employee_id', 'years', 'milestone_date', 'amount', 'awarded_at', 'created_by'];

    protected function casts(): array
    {
        return ['milestone_date' => 'date', 'awarded_at' => 'datetime', 'amount' => 'float'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
