<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FaceSample extends Model
{
    protected $fillable = ['employee_id', 'descriptor', 'score'];

    protected function casts(): array
    {
        return ['descriptor' => 'array', 'score' => 'float'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
