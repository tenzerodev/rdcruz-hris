<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One item in the yearly Learning & Development plan. */
class TrainingProgram extends Model
{
    public const MODES = ['in_house' => 'In-house', 'external' => 'External', 'online' => 'Online'];

    public const STATUSES = ['planned' => 'Planned', 'scheduled' => 'Scheduled', 'done' => 'Completed', 'cancelled' => 'Cancelled'];

    protected $fillable = ['year', 'title', 'competency', 'type', 'mode', 'provider', 'date_from', 'date_to', 'quarter', 'hours', 'slots', 'budget', 'actual_cost', 'status', 'notes'];

    protected function casts(): array
    {
        return ['date_from' => 'date', 'date_to' => 'date', 'budget' => 'float', 'actual_cost' => 'float'];
    }

    public function participants(): HasMany
    {
        return $this->hasMany(TrainingParticipant::class);
    }

    public function needs(): HasMany
    {
        return $this->hasMany(TrainingNeed::class);
    }

    public function getWhenLabelAttribute(): string
    {
        if ($this->date_from) {
            return $this->date_from->format('M j').($this->date_to && $this->date_to->ne($this->date_from) ? '–'.$this->date_to->format('M j') : '').', '.$this->date_from->format('Y');
        }

        return $this->quarter ? 'Q'.$this->quarter.' '.$this->year : (string) $this->year;
    }
}
