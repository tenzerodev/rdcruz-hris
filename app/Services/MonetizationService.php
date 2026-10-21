<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\LeaveLedger;
use App\Models\LeaveMonetization;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Money value of leave credits (CSC Omnibus Rules on Leave, as amended):
 * amount = monthly salary × days × 0.0481927 (the constant factor).
 * - Regular monetization: at least 10 days of VL, keeping at least 5 days of VL.
 * - Special monetization: 50% or more of the credits for health, medical, calamity or other valid reasons.
 * - Terminal leave benefit: all VL and SL at separation × the highest monthly salary received.
 */
class MonetizationService
{
    public const FACTOR = 0.0481927;

    public function __construct(private LeaveService $leaves) {}

    public static function amount(float $salary, float $days): float
    {
        return round($salary * $days * self::FACTOR, 2);
    }

    /** Highest monthly salary on record (current salary or the service record). */
    public function highestSalary(Employee $e): float
    {
        $fromRecords = (float) $e->serviceRecords()->max('annual_salary') / 12;

        return round(max((float) $e->monthly_salary, $fromRecords), 2);
    }

    /** @return array{vl: float, sl: float, days: float, salary: float, amount: float} */
    public function terminalPreview(Employee $e): array
    {
        $b = $this->leaves->balances($e);
        $vl = max(0, $b['VL']);
        $sl = max(0, $b['SL']);
        $salary = $this->highestSalary($e);

        return ['vl' => $vl, 'sl' => $sl, 'days' => $vl + $sl, 'salary' => $salary, 'amount' => self::amount($salary, $vl + $sl)];
    }

    public function monetize(Employee $e, string $kind, float $vl, float $sl, CarbonInterface $date, ?string $reason, User $by): LeaveMonetization
    {
        if (! $e->earnsLeave()) {
            throw ValidationException::withMessages(['employee_id' => $e->type_label.' personnel have no leave credits.']);
        }
        $b = $this->leaves->balances($e);

        if ($kind === 'terminal') {
            $p = $this->terminalPreview($e);
            [$vl, $sl, $salary] = [$p['vl'], $p['sl'], $p['salary']];
            if ($vl + $sl <= 0) {
                throw ValidationException::withMessages(['employee_id' => 'No leave credits left to pay.']);
            }
        } else {
            $salary = (float) $e->monthly_salary;
            if ($vl < 0 || $sl < 0 || $vl + $sl <= 0) {
                throw ValidationException::withMessages(['vl_days' => 'Enter the days to monetize.']);
            }
            if ($vl > $b['VL'] || $sl > $b['SL']) {
                throw ValidationException::withMessages(['vl_days' => "Not enough credits (VL {$b['VL']}, SL {$b['SL']})."]);
            }
            if ($kind === 'regular') {
                if ($sl > 0) {
                    throw ValidationException::withMessages(['sl_days' => 'Regular monetization uses vacation leave only. Use special monetization for sick leave.']);
                }
                if ($vl < 10) {
                    throw ValidationException::withMessages(['vl_days' => 'Regular monetization is at least 10 days of vacation leave.']);
                }
                if ($b['VL'] - $vl < 5) {
                    throw ValidationException::withMessages(['vl_days' => 'At least 5 days of vacation leave must remain after monetization.']);
                }
            } elseif (! $reason) {
                throw ValidationException::withMessages(['reason' => 'Give the reason for special monetization (e.g. health, medical or hospital needs, calamity).']);
            }
            if (! $salary) {
                throw ValidationException::withMessages(['employee_id' => 'No monthly salary on file.']);
            }
        }

        return DB::transaction(function () use ($e, $kind, $vl, $sl, $salary, $date, $reason, $by) {
            $m = LeaveMonetization::create([
                'employee_id' => $e->id, 'kind' => $kind, 'date' => $date->toDateString(), 'vl_days' => $vl, 'sl_days' => $sl,
                'salary' => $salary, 'factor' => self::FACTOR, 'amount' => self::amount($salary, $vl + $sl), 'reason' => $reason, 'created_by' => $by->id,
            ]);
            $label = $kind === 'terminal' ? 'Terminal leave' : 'Monetization';
            foreach (['VL' => $vl, 'SL' => $sl] as $balance => $days) {
                if ($days > 0) {
                    LeaveLedger::create(['employee_id' => $e->id, 'balance' => $balance, 'entry_date' => $date->toDateString(), 'particulars' => $label,
                        'used' => $days, 'source' => 'monetization', 'source_id' => $m->id, 'created_by' => $by->id]);
                }
            }
            AuditLog::record("leave.{$kind}_monetized", $m, ['days' => $vl + $sl, 'amount' => $m->amount]);

            return $m;
        });
    }

    public function undo(LeaveMonetization $m): void
    {
        DB::transaction(function () use ($m) {
            LeaveLedger::where('source', 'monetization')->where('source_id', $m->id)->delete();
            AuditLog::record('leave.monetization_cancelled', $m);
            $m->delete();
        });
    }
}
