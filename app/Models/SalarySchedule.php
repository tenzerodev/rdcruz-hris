<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalarySchedule extends Model
{
    protected $fillable = ['name', 'effective_date', 'basis'];

    private ?array $amountTable = null;

    protected function casts(): array
    {
        return ['effective_date' => 'date'];
    }

    public function rates(): HasMany
    {
        return $this->hasMany(SalaryRate::class);
    }

    /** The schedule in effect on a date (latest effective date on or before it). */
    public static function inEffect(?CarbonInterface $on = null): ?self
    {
        return static::whereDate('effective_date', '<=', ($on ?? today())->toDateString())->orderByDesc('effective_date')->orderByDesc('id')->first();
    }

    public function amount(?int $grade, ?int $step): ?float
    {
        if (! $grade || ! $step) {
            return null;
        }
        $this->amountTable ??= $this->rates()->get()->mapWithKeys(fn ($r) => [$r->grade.'-'.$r->step => (float) $r->amount])->all();

        return $this->amountTable[$grade.'-'.$step] ?? null;
    }
}
