<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WfhReport extends Model
{
    protected $fillable = ['employee_id', 'work_date', 'accomplishments'];

    protected function casts(): array
    {
        return ['work_date' => 'date'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
