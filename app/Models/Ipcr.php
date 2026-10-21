<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Individual Performance Commitment and Review (SPMS). */
class Ipcr extends Model
{
    public const STATUSES = [
        'draft' => 'Drafting targets', 'targets_submitted' => 'Targets for approval', 'targets_approved' => 'Targets approved',
        'self_rated' => 'Self-rated, for rating', 'rated' => 'Rated, for HR', 'final' => 'Final',
    ];

    protected $fillable = ['employee_id', 'performance_period_id', 'rater_id', 'status', 'final_rating', 'adjectival', 'comments',
        'development_plan', 'targets_approved_at', 'rated_at', 'finalized_at'];

    protected function casts(): array
    {
        return ['final_rating' => 'float', 'targets_approved_at' => 'datetime', 'rated_at' => 'datetime', 'finalized_at' => 'datetime'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function rater(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'rater_id');
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(PerformancePeriod::class, 'performance_period_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(IpcrItem::class)->orderByRaw("case category when 'strategic' then 0 when 'core' then 1 else 2 end")->orderBy('sort')->orderBy('id');
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /** Average of the rater's Q/E/T per category, weighted (weights re-spread over categories that have outputs). */
    public function compute(bool $self = false): array
    {
        $weights = config('hris.ipcr.weights');
        $byCat = [];
        foreach ($this->items as $item) {
            $avg = $item->average($self);
            if ($avg !== null) {
                $byCat[$item->category][] = $avg;
            }
        }
        $cats = [];
        foreach ($byCat as $cat => $avgs) {
            $cats[$cat] = round(array_sum($avgs) / count($avgs), 2);
        }
        $w = array_intersect_key($weights, $cats);
        $total = array_sum($w);
        $final = $total > 0 ? round(array_sum(array_map(fn ($c) => $cats[$c] * $w[$c] / $total, array_keys($cats))), 2) : null;

        return ['categories' => $cats, 'final' => $final, 'adjectival' => $final !== null ? self::adjectival($final) : null];
    }

    public static function adjectival(float $rating): string
    {
        foreach (config('hris.ipcr.scale') as [$min, $label]) {
            if ($rating >= $min) {
                return $label;
            }
        }

        return 'Poor';
    }
}
