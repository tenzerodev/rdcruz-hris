<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\Ipcr;
use App\Models\PerformancePeriod;
use App\Support\Notify;
use Illuminate\Http\Request;

class IpcrController extends Controller
{
    public function index(Request $request)
    {
        $periods = PerformancePeriod::orderByDesc('starts_on')->get();
        $period = $periods->firstWhere('id', $request->integer('period')) ?? $periods->first();
        $ipcrs = $period ? Ipcr::with('employee', 'rater')->where('performance_period_id', $period->id)->get()->keyBy('employee_id') : collect();

        return view('admin.ipcr', [
            'periods' => $periods,
            'period' => $period,
            'ipcrs' => $ipcrs,
            'employees' => Employee::where('is_active', true)->orderBy('office')->orderBy('last_name')->get(),
            'summary' => $ipcrs->whereNotNull('adjectival')->countBy('adjectival'),
        ]);
    }

    public function storePeriod(Request $request)
    {
        $period = PerformancePeriod::create($request->validate([
            'name' => ['required', 'string', 'max:100'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after:starts_on'],
        ]) + ['status' => 'planning']);
        AuditLog::record('ipcr.period_created', $period, $period->only('name'));

        return redirect()->route('admin.ipcr.index', ['period' => $period->id])->with('status', 'Rating period created. Employees can now set their targets.');
    }

    public function updatePeriod(Request $request, PerformancePeriod $period)
    {
        $data = $request->validate(['status' => ['required', 'in:'.implode(',', array_keys(PerformancePeriod::STATUSES))]]);
        $period->update($data);
        AuditLog::record('ipcr.period_'.$data['status'], $period);

        return back()->with('status', 'Period is now: '.PerformancePeriod::STATUSES[$data['status']].'.');
    }

    public function finalize(Request $request, Ipcr $ipcr)
    {
        $data = $request->validate(['decision' => ['required', 'in:finalize,reopen']]);
        if ($data['decision'] === 'finalize') {
            abort_unless($ipcr->status === 'rated', 422, 'Only rated IPCRs can be finalized.');
            $ipcr->update(['status' => 'final', 'finalized_at' => now()]);
            Notify::employee($ipcr->employee, 'Your IPCR is final', ["{$ipcr->period->name}: {$ipcr->final_rating} ({$ipcr->adjectival})."], route('me.ipcr.show', $ipcr));
        } else {
            $ipcr->update(['status' => 'self_rated', 'finalized_at' => null]);
        }
        AuditLog::record('ipcr.'.$data['decision'], $ipcr);

        return back()->with('status', $data['decision'] === 'finalize' ? 'IPCR finalized.' : 'Sent back to the rater.');
    }
}
