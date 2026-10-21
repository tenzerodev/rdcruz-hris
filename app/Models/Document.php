<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A file in the employee's 201 folder (stored on the private disk). */
class Document extends Model
{
    public const CATEGORIES = [
        'pds' => 'Personal Data Sheet', 'appointment' => 'Appointment / Assumption', 'contract' => 'Contract / Job Order',
        'saln' => 'SALN', 'ipcr' => 'Performance rating (IPCR)', 'certificate' => 'Certificate / Training', 'clearance' => 'Clearance',
        'id' => 'Government ID', 'medical' => 'Medical', 'other' => 'Other',
    ];

    protected $fillable = ['employee_id', 'category', 'title', 'path', 'original_name', 'mime', 'size', 'uploaded_by'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
