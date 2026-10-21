<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Applicant extends Model
{
    public const STATUSES = [
        'received' => 'Received', 'qualified' => 'Qualified', 'not_qualified' => 'Not qualified', 'shortlisted' => 'Shortlisted',
        'selected' => 'Selected', 'not_selected' => 'Not selected', 'appointed' => 'Appointed', 'withdrawn' => 'Withdrawn',
    ];

    /** What the applicant sees on the status page. */
    public const PUBLIC_STATUS = [
        'received' => 'Received — under initial review', 'qualified' => 'Meets the qualification standards — for assessment',
        'not_qualified' => 'Did not meet the qualification standards', 'shortlisted' => 'Shortlisted — HR will contact you for the next step',
        'selected' => 'Selected — HR will contact you', 'not_selected' => 'Not selected this time. Thank you for applying.',
        'appointed' => 'Appointed', 'withdrawn' => 'Withdrawn',
    ];

    protected $fillable = ['vacancy_id', 'reference_no', 'employee_id', 'last_name', 'first_name', 'middle_name', 'email', 'mobile', 'address',
        'birth_date', 'sex', 'current_position', 'education', 'training', 'experience', 'eligibility', 'documents', 'status', 'remarks', 'consent_at'];

    protected function casts(): array
    {
        return ['birth_date' => 'date', 'documents' => 'array', 'consent_at' => 'datetime', 'files_purged_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (Applicant $a) {
            do {
                $a->reference_no = 'APP-'.now()->format('y').'-'.strtoupper(Str::random(6));
            } while (static::where('reference_no', $a->reference_no)->exists());
        });
    }

    public function vacancy(): BelongsTo
    {
        return $this->belongsTo(Vacancy::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(ApplicantAssessment::class);
    }

    public function getFullNameAttribute(): string
    {
        return mb_strtoupper($this->last_name).', '.$this->first_name.($this->middle_name ? ' '.mb_substr($this->middle_name, 0, 1).'.' : '');
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /**
     * Average of the HRMPSB members' scores, per criterion and in total.
     *
     * @return array{criteria: array<string, float>, total: ?float, assessors: int}
     */
    public function averageScores(): array
    {
        $rows = $this->relationLoaded('assessments') ? $this->assessments : $this->assessments()->get();
        $criteria = [];
        foreach (array_keys(config('hris.recruitment.criteria')) as $key) {
            $criteria[$key] = $rows->isEmpty() ? 0.0 : round($rows->avg(fn ($r) => (float) ($r->scores[$key] ?? 0)), 2);
        }

        return ['criteria' => $criteria, 'total' => $rows->isEmpty() ? null : round($rows->avg('total'), 2), 'assessors' => $rows->count()];
    }
}
