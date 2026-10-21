<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Office (OPCR) or Division (DPCR) Performance Commitment and Review. Prepared by the head,
 * targets approved and accomplishments rated by the Performance Management Team (HR).
 */
class OfficeRating extends Model
{
    public const LEVELS = ['opcr' => 'Office (OPCR)', 'dpcr' => 'Division (DPCR)'];

    public const STATUSES = ['draft' => 'Drafting targets', 'targets_approved' => 'Targets approved', 'rated' => 'Rated, for final', 'final' => 'Final'];

    protected $fillable = ['performance_period_id', 'level', 'office', 'head_id', 'status', 'final_rating', 'adjectival', 'comments',
        'targets_approved_at', 'rated_at', 'finalized_at'];

    protected function casts(): array
    {
        return ['final_rating' => 'float', 'targets_approved_at' => 'datetime', 'rated_at' => 'datetime', 'finalized_at' => 'datetime'];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(PerformancePeriod::class, 'performance_period_id');
    }

    public function head(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'head_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OfficeRatingItem::class)->orderByRaw("case category when 'strategic' then 0 when 'core' then 1 else 2 end")->orderBy('sort')->orderBy('id');
    }

    public function getLevelNameAttribute(): string
    {
        return self::LEVELS[$this->level] ?? $this->level;
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /** Same weighting as the IPCR: average Q/E/T per category, weighted. */
    public function compute(): array
    {
        $weights = config('hris.ipcr.weights');
        $byCat = [];
        foreach ($this->items as $item) {
            if (($avg = $item->average()) !== null) {
                $byCat[$item->category][] = $avg;
            }
        }
        $cats = array_map(fn ($a) => round(array_sum($a) / count($a), 2), $byCat);
        $w = array_intersect_key($weights, $cats);
        $total = array_sum($w);
        $final = $total > 0 ? round(array_sum(array_map(fn ($c) => $cats[$c] * $w[$c] / $total, array_keys($cats))), 2) : null;

        return ['categories' => $cats, 'final' => $final, 'adjectival' => $final !== null ? Ipcr::adjectival($final) : null];
    }

    /**
     * Final IPCR ratings of the office's employees in the same period. SPMS: individual ratings
     * should not, on average, be higher than the office's own rating.
     *
     * @return array{count: int, average: ?float, above: int}
     */
    public function ipcrSummary(): array
    {
        $ratings = Ipcr::where('performance_period_id', $this->performance_period_id)->whereNotNull('final_rating')
            ->whereHas('employee', fn ($q) => $q->where('office', $this->office))->pluck('final_rating');

        return [
            'count' => $ratings->count(),
            'average' => $ratings->isEmpty() ? null : round($ratings->avg(), 2),
            'above' => $this->final_rating ? $ratings->filter(fn ($r) => $r > $this->final_rating)->count() : 0,
        ];
    }

    /** HR (performance) for everything; the head for their own targets and accomplishments. */
    public function canEditBy(User $user): bool
    {
        return $user->canManage('performance') || ($this->head_id && $user->employee?->id === $this->head_id);
    }
}
