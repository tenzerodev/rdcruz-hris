<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A version of the organizational structure: the approved one, or an interim one for a period. */
class OrgStructure extends Model
{
    public const KINDS = ['approved' => 'Approved structure', 'interim' => 'Interim structure'];

    public const STATUSES = ['draft' => 'Draft', 'active' => 'Active', 'archived' => 'Archived'];

    protected $fillable = ['name', 'kind', 'status', 'effective_from', 'effective_to', 'reference', 'notes'];

    protected function casts(): array
    {
        return ['effective_from' => 'date', 'effective_to' => 'date'];
    }

    public function units(): HasMany
    {
        return $this->hasMany(OrgUnit::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(OrgAssignment::class);
    }

    /** In effect on a date: an active interim covering the date wins over the active approved structure. */
    public static function current(?string $date = null): ?self
    {
        $date ??= today()->toDateString();
        $inEffect = fn ($q) => $q->where('status', 'active')
            ->where(fn ($w) => $w->whereNull('effective_from')->orWhereDate('effective_from', '<=', $date))
            ->where(fn ($w) => $w->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date));

        return static::where('kind', 'interim')->tap($inEffect)->latest('effective_from')->first()
            ?? static::where('kind', 'approved')->tap($inEffect)->latest('effective_from')->first();
    }

    public function isCurrent(): bool
    {
        return static::current()?->id === $this->id;
    }

    public function getKindLabelAttribute(): string
    {
        return self::KINDS[$this->kind] ?? $this->kind;
    }
}
