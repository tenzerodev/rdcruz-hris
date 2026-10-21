<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnnouncementRead extends Model
{
    protected $fillable = ['announcement_id', 'employee_id', 'read_at', 'acknowledged_at'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime', 'acknowledged_at' => 'datetime'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class);
    }
}
