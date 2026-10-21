<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/** A memo, office order, circular or announcement for all employees or some offices / groups. */
class Announcement extends Model
{
    public const KINDS = ['memo' => 'Memorandum', 'office_order' => 'Office Order', 'circular' => 'Circular', 'announcement' => 'Announcement'];

    protected $fillable = ['kind', 'reference_no', 'title', 'body', 'attachment_path', 'attachment_name', 'audience', 'requires_ack', 'pinned',
        'published_at', 'expires_on', 'created_by'];

    protected function casts(): array
    {
        return ['audience' => 'array', 'requires_ack' => 'boolean', 'pinned' => 'boolean', 'published_at' => 'datetime', 'expires_on' => 'date'];
    }

    public function reads(): HasMany
    {
        return $this->hasMany(AnnouncementRead::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getKindNameAttribute(): string
    {
        return self::KINDS[$this->kind] ?? $this->kind;
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null && $this->published_at->lte(now());
    }

    /** Published and not expired. */
    public function scopeLive(Builder $q): Builder
    {
        return $q->whereNotNull('published_at')->where('published_at', '<=', now())
            ->where(fn ($w) => $w->whereNull('expires_on')->orWhereDate('expires_on', '>=', today()));
    }

    public function isFor(Employee $e): bool
    {
        $offices = $this->audience['offices'] ?? [];
        $groups = $this->audience['groups'] ?? [];

        return (! $offices || in_array($e->office, $offices, true)) && (! $groups || in_array($e->group, $groups, true));
    }

    /** Active employees this is addressed to. */
    public function recipients(): Collection
    {
        return Employee::where('is_active', true)
            ->when($this->audience['offices'] ?? [], fn ($q, $o) => $q->whereIn('office', $o))
            ->orderBy('last_name')->orderBy('first_name')->get()
            ->filter(fn (Employee $e) => $this->isFor($e))->values();
    }

    public function getAudienceLabelAttribute(): string
    {
        $parts = array_filter([
            implode(', ', $this->audience['offices'] ?? []),
            implode(', ', array_map(fn ($g) => config("hris.groups.$g", $g), $this->audience['groups'] ?? [])),
        ]);

        return $parts ? implode(' · ', $parts) : 'All employees';
    }

    /** Live announcements for this employee, newest (and pinned) first. */
    public static function forEmployee(Employee $e): Collection
    {
        return static::live()->orderByDesc('pinned')->orderByDesc('published_at')->get()->filter(fn ($a) => $a->isFor($e))->values();
    }
}
