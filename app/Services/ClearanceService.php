<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Clearance;
use App\Models\ClearanceItem;
use App\Models\Employee;
use App\Models\IssuedDevice;
use App\Models\ServiceRecord;
use App\Models\User;
use App\Models\WfhArrangement;
use App\Support\Notify;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Exit clearance: office sign-offs, then separation (record closed, login and devices disabled). */
class ClearanceService
{
    public function start(Employee $e, CarbonInterface $date, string $reason, User $by): Clearance
    {
        if (Clearance::where('employee_id', $e->id)->where('status', 'open')->exists()) {
            throw ValidationException::withMessages(['employee_id' => "{$e->full_name} already has an open clearance."]);
        }

        return DB::transaction(function () use ($e, $date, $reason, $by) {
            $c = Clearance::create(['employee_id' => $e->id, 'separation_date' => $date->toDateString(), 'reason' => $reason, 'created_by' => $by->id]);
            $devices = $e->issuedDevices()->where('is_active', true)->pluck('name')->all();
            foreach (config('hris.clearance_offices') as $i => $o) {
                $checks = $o['checks'];
                if ($o['office'] === 'Property / Supply' && $devices) {
                    $checks .= ' — issued: '.implode(', ', $devices);
                }
                $c->items()->create(['office' => $o['office'], 'checks' => $checks, 'sort' => $i]);
            }
            AuditLog::record('clearance.started', $c, ['employee' => $e->id, 'reason' => $reason]);
            Notify::employee($e, 'Your exit clearance has started', [
                'HR started your clearance for separation on '.$date->format('F j, Y')." ({$reason}).",
                'Please settle your accountabilities with each office. You can follow the progress in the HRIS.',
            ], route('me.index'));

            return $c;
        });
    }

    public function sign(ClearanceItem $item, string $name, ?string $remarks, User $by): void
    {
        abort_unless($item->clearance->status === 'open', 422, 'This clearance is closed.');
        $item->update(['cleared_at' => now(), 'cleared_by' => $name, 'user_id' => $by->id, 'remarks' => $remarks]);
        AuditLog::record('clearance.signed', $item->clearance, ['office' => $item->office, 'by' => $name]);
    }

    public function unsign(ClearanceItem $item): void
    {
        abort_unless($item->clearance->status === 'open', 422, 'This clearance is closed.');
        $item->update(['cleared_at' => null, 'cleared_by' => null, 'user_id' => null]);
        AuditLog::record('clearance.unsigned', $item->clearance, ['office' => $item->office]);
    }

    /** All offices signed: separate the employee. */
    public function complete(Clearance $c, User $by): void
    {
        $c->load('items', 'employee');
        if (! $c->isDone()) {
            throw ValidationException::withMessages(['clearance' => 'Not all offices have cleared the employee yet.']);
        }
        DB::transaction(function () use ($c, $by) {
            $e = $c->employee;
            $date = $c->separation_date;
            $e->forceFill(['is_active' => false, 'date_separated' => $date->toDateString(), 'separation_reason' => $c->reason])->save();
            $open = ServiceRecord::where('employee_id', $e->id)->whereNull('date_to')->orderByDesc('date_from')->first();
            $open?->update(['date_to' => $date->toDateString(), 'separation' => $date->format('m/d/Y').' — '.$c->reason]);
            $e->user?->forceFill(['disabled_at' => now()])->save();
            IssuedDevice::where('employee_id', $e->id)->update(['is_active' => false]);
            WfhArrangement::where('employee_id', $e->id)->whereDate('date_to', '>', $date->toDateString())->update(['date_to' => $date->toDateString()]);
            $c->update(['status' => 'completed', 'completed_at' => now()]);
            AuditLog::record('clearance.completed', $c, ['employee' => $e->id, 'by' => $by->id]);
        });
    }

    public function cancel(Clearance $c): void
    {
        abort_unless($c->status === 'open', 422);
        $c->update(['status' => 'cancelled']);
        AuditLog::record('clearance.cancelled', $c);
    }
}
