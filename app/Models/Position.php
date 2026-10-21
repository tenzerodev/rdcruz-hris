<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** A plantilla item. */
class Position extends Model
{
    protected $fillable = ['item_no', 'title', 'salary_grade', 'office', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'salary_grade' => 'integer'];
    }

    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    public function vacancies(): HasMany
    {
        return $this->hasMany(Vacancy::class);
    }
}
