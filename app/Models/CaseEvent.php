<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseEvent extends Model
{
    public const TYPES = [
        'complaint' => 'Complaint filed', 'show_cause' => 'Show-cause memo / order to comment', 'comment' => 'Comment / counter-affidavit received',
        'formal_charge' => 'Formal charge served', 'answer' => 'Answer received', 'pre_hearing' => 'Pre-hearing conference', 'hearing' => 'Hearing',
        'position_paper' => 'Position paper', 'report' => 'Investigation report', 'decision' => 'Decision served', 'motion' => 'Motion for reconsideration',
        'appeal' => 'Appeal (CSC)', 'note' => 'Note',
    ];

    protected $fillable = ['admin_case_id', 'date', 'type', 'details', 'due_on', 'document_path', 'document_name', 'created_by'];

    protected function casts(): array
    {
        return ['date' => 'date', 'due_on' => 'date'];
    }

    public function adminCase(): BelongsTo
    {
        return $this->belongsTo(AdminCase::class);
    }

    public function getTypeNameAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }
}
