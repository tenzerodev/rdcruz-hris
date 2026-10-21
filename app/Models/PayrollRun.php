<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollRun extends Model
{
    public const CUTS = ['full' => 'Whole month', 'first' => '1st half (1–15)', 'second' => '2nd half (16–end)',
        'mid_year' => 'Mid-Year Bonus', 'year_end' => 'Year-End Bonus & Cash Gift'];

    public function isBonus(): bool
    {
        return in_array($this->cut, ['mid_year', 'year_end'], true);
    }

    protected $fillable = ['period', 'cut', 'pay_date', 'status', 'notes', 'created_by'];

    protected function casts(): array
    {
        return ['pay_date' => 'date', 'released_at' => 'datetime'];
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }

    public function releaser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }

    public function month(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d', $this->period.'-01')->startOfDay();
    }

    public function isReleased(): bool
    {
        return $this->status === 'released';
    }

    /** e.g. "October 2026" or "October 1–15, 2026". */
    public function getLabelAttribute(): string
    {
        $m = $this->month();

        return match ($this->cut) {
            'mid_year' => 'Mid-Year Bonus '.$m->year,
            'year_end' => 'Year-End Bonus & Cash Gift '.$m->year,
            'first' => $m->format('F').' 1–15, '.$m->year,
            'second' => $m->format('F').' 16–'.$m->daysInMonth.', '.$m->year,
            default => $m->format('F Y'),
        };
    }
}
