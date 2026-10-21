<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Ipcr;
use App\Models\LeaveApplication;
use App\Models\OrgStructure;
use App\Models\Punch;
use App\Models\Setting;
use App\Models\TravelOrder;
use App\Models\WfhReport;
use App\Services\LeaveService;
use App\Services\OrgService;
use Illuminate\Http\Request;

/** Printable forms, for the employee, their supervisor, or HR. */
class PrintController extends Controller
{
    public function leaveForm(Request $request, LeaveApplication $leave, LeaveService $leaves)
    {
        abort_unless($request->user()->canView($leave->employee), 403);

        return view('print.form6', [
            'leave' => $leave->load('employee', 'recommender', 'approver'),
            'balances' => $leave->employee->earnsLeave() ? $leaves->balances($leave->employee) : null,
            'settings' => Setting::allValues(),
        ]);
    }

    public function serviceRecord(Request $request, Employee $employee)
    {
        abort_unless($request->user()->canView($employee), 403);

        return view('print.service-record', ['employee' => $employee->load('serviceRecords'), 'settings' => Setting::allValues()]);
    }

    public function certificate(Request $request, Employee $employee)
    {
        abort_unless($request->user()->canSee('people'), 403);

        return view('print.coe', ['employee' => $employee, 'settings' => Setting::allValues(), 'purpose' => $request->query('purpose')]);
    }

    public function ipcr(Request $request, Ipcr $ipcr)
    {
        abort_unless($request->user()->canView($ipcr->employee), 403);

        return view('print.ipcr', ['ipcr' => $ipcr->load('items', 'employee', 'rater', 'period'), 'rating' => $ipcr->compute(), 'settings' => Setting::allValues()]);
    }

    public function pds(Request $request, Employee $employee)
    {
        abort_unless($request->user()->canView($employee), 403);

        return view('print.pds', ['e' => $employee->load('educations', 'eligibilities', 'trainings', 'serviceRecords'), 'p' => $employee->pds ?? [], 'settings' => Setting::allValues()]);
    }

    public function orgChart(Request $request, OrgStructure $structure, OrgService $org)
    {
        abort_unless($request->user()->canSee('people') || $structure->status === 'active', 403);

        return view('print.org-chart', ['s' => $structure, 'tree' => $org->tree($structure), 'settings' => Setting::allValues()]);
    }

    /** Monthly work-from-home accomplishment report, for the employee and supervisor to sign. */
    public function wfhReport(Request $request, Employee $employee)
    {
        abort_unless($request->user()->canView($employee), 403);
        $month = $request->date('month', 'Y-m') ?? now()->startOfMonth();
        $reports = WfhReport::where('employee_id', $employee->id)->whereYear('work_date', $month->year)->whereMonth('work_date', $month->month)
            ->orderBy('work_date')->get();
        $punches = Punch::where('employee_id', $employee->id)->where('source', 'wfh')
            ->whereYear('work_date', $month->year)->whereMonth('work_date', $month->month)->orderBy('punched_at')->get()
            ->groupBy(fn ($p) => $p->work_date->toDateString());

        return view('print.wfh-report', ['employee' => $employee->load('supervisor'), 'month' => $month, 'reports' => $reports, 'punches' => $punches, 'settings' => Setting::allValues()]);
    }

    public function travelOrder(Request $request, TravelOrder $order)
    {
        abort_unless($order->employees->contains(fn ($e) => $request->user()->canView($e)), 403);

        return view('print.travel-order', ['order' => $order->load('employees', 'approver'), 'settings' => Setting::allValues()]);
    }
}
