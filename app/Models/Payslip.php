<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payslip extends Model
{
    protected $fillable = ['payroll_run_id', 'employee_id', 'inputs', 'earnings', 'deductions', 'employer_share', 'gross', 'total_deductions', 'net', 'taxable', 'tax'];

    protected function casts(): array
    {
        return [
            'inputs' => 'array',
            'earnings' => 'array',
            'deductions' => 'array',
            'employer_share' => 'array',
            'gross' => 'float',
            'total_deductions' => 'float',
            'net' => 'float',
            'taxable' => 'float',
            'tax' => 'float',
            'viewed_at' => 'datetime',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** @return list<array{label: string, amount: float, taxable?: bool}> */
    public function manualEarnings(): array
    {
        return array_values(array_filter($this->earnings ?? [], fn ($e) => ! empty($e['manual'])));
    }

    /** @return list<array{label: string, amount: float}> */
    public function manualDeductions(): array
    {
        return array_values(array_filter($this->deductions ?? [], fn ($d) => ! empty($d['manual'])));
    }

    /**
     * Year-to-date totals up to and including this payslip's period (released payrolls only).
     *
     * @return array{gross: float, tax: float}
     */
    public function yearToDate(): array
    {
        $period = $this->run->period;
        $slips = static::where('employee_id', $this->employee_id)
            ->whereHas('run', fn ($q) => $q->where('status', 'released')->where('period', 'like', substr($period, 0, 4).'-%')->where('period', '<=', $period))
            ->get();
        if (! $this->run->isReleased()) {
            $slips->push($this); // a draft shows what YTD will be once released
        }

        return ['gross' => $slips->sum('gross'), 'tax' => $slips->sum('tax')];
    }

    /** Payslips are private: the employee, and staff with payroll access. Supervisors don't see pay. */
    public function canBeSeenBy(User $user): bool
    {
        return $user->canSee('payroll') || ($user->employee?->id === $this->employee_id && $this->run->isReleased());
    }
}
