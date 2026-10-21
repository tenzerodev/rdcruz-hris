<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Eligibility extends Model
{
    protected $fillable = ['employee_id', 'name', 'rating', 'exam_date', 'exam_place', 'license_no', 'valid_until'];

    protected function casts(): array
    {
        return ['exam_date' => 'date', 'valid_until' => 'date'];
    }
}
