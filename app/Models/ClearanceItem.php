<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClearanceItem extends Model
{
    protected $fillable = ['clearance_id', 'office', 'checks', 'sort', 'cleared_at', 'cleared_by', 'user_id', 'remarks'];

    protected function casts(): array
    {
        return ['cleared_at' => 'datetime'];
    }

    public function clearance(): BelongsTo
    {
        return $this->belongsTo(Clearance::class);
    }
}
