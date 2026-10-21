<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Education extends Model
{
    protected $table = 'educations';

    public const LEVELS = ['elementary' => 'Elementary', 'secondary' => 'Secondary', 'vocational' => 'Vocational / Trade Course', 'college' => 'College', 'graduate' => 'Graduate Studies'];

    protected $fillable = ['employee_id', 'level', 'school', 'degree', 'year_from', 'year_to', 'year_graduated', 'honors'];
}
