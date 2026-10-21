<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One line of an employee's leave card (VL, SL, SPL or COC hours). */
class LeaveLedger extends Model
{
    protected $table = 'leave_ledger';

    protected $fillable = ['employee_id', 'balance', 'entry_date', 'period', 'particulars', 'earned', 'used', 'source', 'source_id', 'created_by'];

    protected function casts(): array
    {
        return ['entry_date' => 'date', 'earned' => 'float', 'used' => 'float'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
