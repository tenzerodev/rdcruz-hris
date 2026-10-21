<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    protected $fillable = ['date', 'name', 'type'];

    public const TYPES = ['regular' => 'Regular holiday', 'special' => 'Special non-working day', 'suspension' => 'Work suspension'];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }
}
