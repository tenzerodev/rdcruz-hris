<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalaryRate extends Model
{
    public $timestamps = false;

    protected $fillable = ['salary_schedule_id', 'grade', 'step', 'amount'];

    protected function casts(): array
    {
        return ['amount' => 'float'];
    }
}
