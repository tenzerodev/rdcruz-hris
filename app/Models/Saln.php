<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Saln extends Model
{
    public const FILING = ['joint' => 'Joint filing', 'separate' => 'Separate filing', 'not_applicable' => 'Not applicable (single)'];

    protected $fillable = ['employee_id', 'year', 'filed_on', 'filing', 'remarks', 'received_by'];

    protected function casts(): array
    {
        return ['filed_on' => 'date'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
