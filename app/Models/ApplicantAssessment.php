<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicantAssessment extends Model
{
    protected $fillable = ['applicant_id', 'assessor', 'scores', 'total', 'remarks', 'entered_by'];

    protected function casts(): array
    {
        return ['scores' => 'array', 'total' => 'float'];
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class);
    }
}
