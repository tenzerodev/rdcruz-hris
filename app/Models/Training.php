<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Training extends Model
{
    public const TYPES = ['foundation' => 'Foundation', 'technical' => 'Technical', 'supervisory' => 'Supervisory', 'managerial' => 'Managerial'];

    protected $fillable = ['employee_id', 'title', 'date_from', 'date_to', 'hours', 'type', 'sponsor'];

    protected function casts(): array
    {
        return ['date_from' => 'date', 'date_to' => 'date'];
    }
}
