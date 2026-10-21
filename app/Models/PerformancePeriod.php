<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PerformancePeriod extends Model
{
    public const STATUSES = ['planning' => 'Setting targets', 'rating' => 'Rating accomplishments', 'closed' => 'Closed'];

    protected $fillable = ['name', 'starts_on', 'ends_on', 'status'];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date'];
    }

    public function ipcrs(): HasMany
    {
        return $this->hasMany(Ipcr::class);
    }

    public static function current(): ?self
    {
        return static::where('status', '!=', 'closed')->orderByDesc('starts_on')->first();
    }
}
