<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Ipcr;
use App\Models\LeaveApplication;
use App\Models\OvertimeAuthorization;
use App\Models\PassSlip;
use App\Models\PerformancePeriod;
use App\Models\Saln;
use App\Models\TravelOrder;
use App\Support\Notify;
use Illuminate\Support\Facades\Cache;

/**
 * Daily reminders (php artisan hris:reminders): waiting approvals, step increments due, SALN and
 * IPCR deadlines. Each reminder is sent at most once per day, so running it twice is harmless.
 */
class Reminders
{
    public function __construct(private CompensationService $compensation) {}

    /** @return array<string, int> how many reminders of each kind were sent */
    public function send(): array
    {
        return [
            'approvals' => $this->approvals(),
            'steps' => $this->stepIncrements(),
            'saln' => $this->saln(),
            'ipcr' => $this->ipcr(),
        ];
    }

    /** Supervisors with requests waiting more than 2 days. */
    private function approvals(): int
    {
        $before = now()->subDays(2);
        $sent = 0;
        Employee::where('is_active', true)->whereHas('subordinates')->with('user')->get()->each(function (Employee $boss) use ($before, &$sent) {
            $ids = $boss->subordinates()->pluck('id');
            $count = LeaveApplication::whereIn('employee_id', $ids)->where('status', 'pending')->where('created_at', '<', $before)->count()
                + OvertimeAuthorization::whereIn('employee_id', $ids)->where('status', 'pending')->where('created_at', '<', $before)->count()
                + PassSlip::whereIn('employee_id', $ids)->where('status', 'pending')->where('created_at', '<', $before)->count()
                + TravelOrder::where('status', 'pending')->where('created_at', '<', $before)->whereHas('employees', fn ($q) => $q->whereIn('employees.id', $ids))->count();
            if ($count && $this->once("approvals.{$boss->id}")) {
                Notify::employee($boss, "{$count} request(s) waiting for your approval", ['Some requests from your team have been waiting for more than 2 days.'], route('me.approvals'));
                $sent++;
            }
        });

        return $sent;
    }

    /** HR: step increments due within 30 days (weekly, Mondays). */
    private function stepIncrements(): int
    {
        if (! today()->isMonday()) {
            return 0;
        }
        $due = $this->compensation->stepIncrements(30)->filter(fn ($r) => ! $r['blocker'] && $r['due']->gte(today()->subDays(30)));
        if ($due->isEmpty() || ! $this->once('steps')) {
            return 0;
        }
        Notify::hr('Step increments due ('.$due->count().')', $due->take(15)->map(fn ($r) => $r['employee']->full_name.' — step '.$r['new_step'].' due '.$r['due']->format('M j, Y'))->values()->all(),
            route('admin.compensation.index'), 'people');

        return 1;
    }

    /** Plantilla employees who have not filed last year's SALN: every Monday in April, and on April 25. */
    private function saln(): int
    {
        $d = today();
        if ($d->month !== 4 || (! $d->isMonday() && $d->day !== 25)) {
            return 0;
        }
        $year = $d->year - 1;
        $filed = Saln::where('year', $year)->pluck('employee_id');
        $sent = 0;
        Employee::where('is_active', true)->whereNotIn('id', $filed)->get()
            ->filter(fn (Employee $e) => $e->rules()['group'] === 'plantilla')
            ->each(function (Employee $e) use ($year, &$sent) {
                if ($this->once("saln.{$e->id}")) {
                    Notify::employee($e, "Reminder: file your {$year} SALN by April 30", ["Your Statement of Assets, Liabilities and Net Worth for {$year} has not been recorded yet. Submit it to HR on or before April 30."]);
                    $sent++;
                }
            });

        return $sent;
    }

    /** IPCR: targets not submitted, or ratings not done, with the period ending within 14 days. */
    private function ipcr(): int
    {
        $sent = 0;
        foreach (PerformancePeriod::where('status', '!=', 'closed')->get() as $period) {
            $daysLeft = today()->diffInDays($period->ends_on, false);
            if ($daysLeft < 0 || $daysLeft > 14 || (! today()->isMonday() && $daysLeft !== 3)) {
                continue;
            }
            $started = Ipcr::where('performance_period_id', $period->id)->whereNotIn('status', ['draft'])->pluck('employee_id');
            Employee::where('is_active', true)->whereNotNull('user_id')->whereNotIn('id', $started)->get()
                ->filter(fn (Employee $e) => $e->rules()['group'] === 'plantilla')
                ->each(function (Employee $e) use ($period, &$sent) {
                    if ($this->once("ipcr.{$period->id}.{$e->id}")) {
                        Notify::employee($e, "IPCR: {$period->name} ends ".$period->ends_on->format('M j'), ['Submit your IPCR targets to your supervisor in the HRIS.'], route('me.ipcr'));
                        $sent++;
                    }
                });
        }

        return $sent;
    }

    private function once(string $key): bool
    {
        return Cache::add('hris.reminder.'.$key.'.'.today()->toDateString(), true, now()->addDays(2));
    }
}
