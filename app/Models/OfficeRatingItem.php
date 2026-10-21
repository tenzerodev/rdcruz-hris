<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfficeRatingItem extends Model
{
    protected $fillable = ['office_rating_id', 'category', 'output', 'success_indicator', 'budget', 'accountable', 'accomplishment', 'q', 'e', 't', 'remarks', 'sort'];

    protected function casts(): array
    {
        return ['budget' => 'float'];
    }

    public function rating(): BelongsTo
    {
        return $this->belongsTo(OfficeRating::class, 'office_rating_id');
    }

    public function average(): ?float
    {
        $vals = array_filter([$this->q, $this->e, $this->t], fn ($v) => $v !== null);

        return $vals ? round(array_sum($vals) / count($vals), 2) : null;
    }
}
