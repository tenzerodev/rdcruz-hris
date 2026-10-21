<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrgUnit extends Model
{
    /** Levels from the top. A unit's parent must be a higher level (levels can be skipped). */
    public const LEVELS = ['office' => 'Office', 'department' => 'Department', 'division' => 'Division', 'unit' => 'Unit'];

    protected $fillable = ['org_structure_id', 'parent_id', 'level', 'code', 'name', 'head_employee_id', 'head_title', 'sort'];

    public function structure(): BelongsTo
    {
        return $this->belongsTo(OrgStructure::class, 'org_structure_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(OrgUnit::class, 'parent_id')->orderBy('sort')->orderBy('name');
    }

    public function head(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'head_employee_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(OrgAssignment::class);
    }

    public static function rank(string $level): int
    {
        return array_search($level, array_keys(self::LEVELS), true);
    }

    public function getLevelLabelAttribute(): string
    {
        return self::LEVELS[$this->level] ?? $this->level;
    }

    /** Units from the top down to this one. */
    public function path(): array
    {
        $chain = [];
        for ($u = $this; $u; $u = $u->parent) {
            array_unshift($chain, $u);
        }

        return $chain;
    }

    /** The unit of a given level above (or at) this one, e.g. its department. */
    public function ancestorAt(string $level): ?self
    {
        foreach ($this->path() as $u) {
            if ($u->level === $level) {
                return $u;
            }
        }

        return null;
    }
}
