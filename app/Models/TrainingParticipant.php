<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingParticipant extends Model
{
    public const STATUSES = ['nominated' => 'Nominated', 'attended' => 'Attended', 'absent' => 'Did not attend'];

    protected $fillable = ['training_program_id', 'employee_id', 'status', 'training_id'];

    public function program(): BelongsTo
    {
        return $this->belongsTo(TrainingProgram::class, 'training_program_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
