<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A position open for hiring. Open + public vacancies show on the careers page. */
class Vacancy extends Model
{
    public const STATUSES = ['draft' => 'Draft', 'open' => 'Open', 'closed' => 'Closed (assessment)', 'filled' => 'Filled', 'cancelled' => 'Cancelled'];

    protected $fillable = ['position_id', 'title', 'item_no', 'salary_grade', 'monthly_salary', 'employment_type', 'place_of_assignment',
        'education', 'training', 'experience', 'eligibility', 'competencies', 'instructions', 'slots', 'posted_on', 'closing_on', 'status', 'public', 'created_by'];

    protected function casts(): array
    {
        return ['posted_on' => 'date', 'closing_on' => 'date', 'public' => 'boolean', 'monthly_salary' => 'float'];
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function applicants(): HasMany
    {
        return $this->hasMany(Applicant::class);
    }

    /** Accepting applications today. */
    public function isAccepting(): bool
    {
        return $this->status === 'open' && (! $this->posted_on || $this->posted_on->lte(today())) && (! $this->closing_on || $this->closing_on->gte(today()));
    }

    public function scopeAccepting(Builder $q): Builder
    {
        return $q->where('status', 'open')->where('public', true)
            ->where(fn ($w) => $w->whereNull('posted_on')->orWhereDate('posted_on', '<=', today()))
            ->where(fn ($w) => $w->whereNull('closing_on')->orWhereDate('closing_on', '>=', today()));
    }

    public function getTypeLabelAttribute(): string
    {
        return config("hris.employment_types.{$this->employment_type}.label", $this->employment_type);
    }
}
