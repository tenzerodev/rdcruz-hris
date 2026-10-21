<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A loan or other recurring payroll deduction, taken from every regular payslip until paid or stopped. */
class Loan extends Model
{
    public const KINDS = ['loan' => 'Loan (has a total amount)', 'contribution' => 'Recurring contribution (no end)'];

    public const STATUSES = ['active' => 'Active', 'stopped' => 'Stopped', 'paid' => 'Fully paid'];

    protected $fillable = ['employee_id', 'lender', 'kind', 'name', 'reference_no', 'total_amount', 'amortization', 'start_period', 'end_period', 'status', 'notes', 'created_by'];

    protected function casts(): array
    {
        return ['total_amount' => 'float', 'amortization' => 'float'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(LoanPayment::class);
    }

    public static function lenders(): array
    {
        return config('hris.payroll.lenders');
    }

    public function getLenderNameAttribute(): string
    {
        return self::lenders()[$this->lender] ?? $this->lender;
    }

    public function paid(): float
    {
        return round((float) ($this->payments_sum_amount ?? $this->payments()->sum('amount')), 2);
    }

    /** What is still owed, or null for a contribution with no total. */
    public function balance(): ?float
    {
        return $this->total_amount === null ? null : round(max(0, $this->total_amount - $this->paid()), 2);
    }

    /** Deducted in this payroll month? */
    public function appliesTo(string $period): bool
    {
        return $this->status === 'active' && $this->start_period <= $period && (! $this->end_period || $this->end_period >= $period);
    }

    /** Payslip line label, e.g. "GSIS Consolidated Loan (#1234)". */
    public function getLineLabelAttribute(): string
    {
        return $this->name.($this->reference_no ? ' (#'.$this->reference_no.')' : '');
    }
}
