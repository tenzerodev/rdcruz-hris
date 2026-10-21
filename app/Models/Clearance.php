<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Clearance extends Model
{
    protected $fillable = ['employee_id', 'separation_date', 'reason', 'status', 'completed_at', 'created_by'];

    protected function casts(): array
    {
        return ['separation_date' => 'date', 'completed_at' => 'datetime'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ClearanceItem::class)->orderBy('sort');
    }

    public function isDone(): bool
    {
        return $this->items->every(fn ($i) => $i->cleared_at !== null);
    }
}
