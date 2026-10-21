<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\OvertimeAuthorization;
use App\Models\Setting;
use App\Services\DtrBuilder;
use App\Support\Notify;
use Illuminate\Http\Request;

class OvertimeController extends Controller
{
    public function index(Request $request)
    {
        $month = $request->date('month', 'Y-m') ?? now();
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        return view('admin.overtime.index', [
            'month' => $month,
            'authorizations' => OvertimeAuthorization::with('employee', 'approver:id,name')->where('status', 'approved')
                ->whereDate('date_from', '<=', $end)->whereDate('date_to', '>=', $start)
                ->orderBy('date_from')->get(),
            'employees' => Employee::where('is_active', true)->orderBy('last_name')->orderBy('first_name')->get(),
            'offices' => Employee::whereNotNull('office')->distinct()->orderBy('office')->pluck('office'),
            'requiresApproval' => Setting::get('ot_requires_approval') === '1',
            'pending' => OvertimeAuthorization::with('employee')->where('status', 'pending')->orderBy('date_from')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_ids' => ['required', 'array', 'min:1'],
            'employee_ids.*' => ['integer', 'exists:employees,id'],
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'max_hours_per_day' => ['nullable', 'numeric', 'min:0.5', 'max:16'],
            'purpose' => ['required', 'string', 'max:250'],
            'reference_no' => ['nullable', 'string', 'max:100'],
        ], ['employee_ids.required' => 'Choose at least one employee.']);

        foreach ($data['employee_ids'] as $id) {
            $auth = OvertimeAuthorization::create([
                'employee_id' => $id,
                'date_from' => $data['date_from'],
                'date_to' => $data['date_to'],
                'max_minutes_per_day' => isset($data['max_hours_per_day']) ? (int) round($data['max_hours_per_day'] * 60) : null,
                'purpose' => $data['purpose'],
                'reference_no' => $data['reference_no'] ?? null,
                'status' => 'approved',
                'requested_by' => $request->user()->id,
                'approved_by' => $request->user()->id,
                'decided_at' => now(),
            ]);
            AuditLog::record('overtime.authorized', $auth, collect($data)->except('employee_ids')->all());
        }

        return back()->with('status', 'Overtime authority saved for '.count($data['employee_ids']).' employee(s).');
    }

    public function decide(Request $request, OvertimeAuthorization $authorization)
    {
        $data = $request->validate(['decision' => ['required', 'in:approve,disapprove'], 'remarks' => ['nullable', 'string', 'max:250'], 'reference_no' => ['nullable', 'string', 'max:100']]);
        $authorization->update([
            'status' => $data['decision'] === 'approve' ? 'approved' : 'disapproved',
            'approved_by' => $request->user()->id, 'decided_at' => now(),
            'remarks' => $data['remarks'] ?? null, 'reference_no' => $data['reference_no'] ?? $authorization->reference_no,
        ]);
        AuditLog::record('overtime.'.$authorization->status, $authorization);
        Notify::employee($authorization->employee, 'Your overtime request was '.$authorization->status,
            ["Overtime {$authorization->date_from->format('M j')}".($authorization->date_to->ne($authorization->date_from) ? '–'.$authorization->date_to->format('M j') : '').": {$authorization->purpose}."], route('me.overtime'));

        return back()->with('status', 'Overtime request '.$authorization->status.'.');
    }

    public function destroy(OvertimeAuthorization $authorization)
    {
        AuditLog::record('overtime.revoked', $authorization, $authorization->only('employee_id', 'purpose', 'reference_no'));
        $authorization->delete();

        return back()->with('status', 'Overtime authority removed.');
    }

    public function report(Request $request, DtrBuilder $builder)
    {
        $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'office' => ['nullable', 'string'],
            'employee_id' => ['nullable', 'integer'],
            'format' => ['nullable', 'in:html,csv'],
        ]);
        $month = $request->date('month', 'Y-m');

        $records = Employee::query()
            ->when($request->integer('employee_id'), fn ($q, $id) => $q->whereKey($id), fn ($q) => $q->where('is_active', true))
            ->when($request->input('office'), fn ($q, $o) => $q->where('office', $o))
            ->orderBy('last_name')->orderBy('first_name')->get()
            ->map(fn ($e) => $builder->build($e, $month->year, $month->month))
            ->filter(fn ($r) => $r['overtime']['actual'] > 0 || $request->integer('employee_id'))
            ->values();

        if ($request->input('format') === 'csv') {
            return response()->streamDownload(function () use ($records) {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['Employee No', 'Name', 'Office', 'Date', 'Day type', 'AM In', 'PM Out', 'OT rendered (h)', 'OT credited (h)', 'COC (h)', 'Authority']);
                foreach ($records as $r) {
                    foreach ($r['rows'] as $row) {
                        if (! $row['ot_actual']) {
                            continue;
                        }
                        fputcsv($out, [
                            $r['employee']->employee_no, $r['employee']->full_name, $r['employee']->office,
                            $row['date']->toDateString(), $row['is_workday'] ? 'Work day' : 'Rest day/holiday',
                            $row['am_in'], $row['pm_out'],
                            round($row['ot_actual'] / 60, 2), round($row['ot_credited'] / 60, 2), round($row['ot_coc'] / 60, 2),
                            $row['ot_authorization']?->reference_no ?? ($row['ot_authorization'] ? 'yes' : ''),
                        ]);
                    }
                }
                fclose($out);
            }, 'overtime-'.$month->format('Y-m').'.csv', ['Content-Type' => 'text/csv']);
        }

        return view('admin.overtime.report', [
            'records' => $records,
            'month' => $month,
            'settings' => Setting::allValues(),
            'rules' => $builder->overtimeRules(),
        ]);
    }
}
