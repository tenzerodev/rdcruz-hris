<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\PayrollInputs;
use Illuminate\Http\Request;

class PayrollController extends Controller
{
    public function index(Request $request, PayrollInputs $payroll)
    {
        $month = $request->date('month', 'Y-m') ?? now()->subMonthNoOverflow();
        $rows = $payroll->rows($month->year, $month->month);

        return view('admin.payroll', [
            'month' => $month,
            'rows' => $rows,
            'gsis' => config('hris.payroll'),
        ]);
    }

    public function export(Request $request, PayrollInputs $payroll)
    {
        $data = $request->validate(['month' => ['required', 'date_format:Y-m'], 'format' => ['required', 'in:csv,json'], 'frequency' => ['nullable', 'in:monthly,semi-monthly']]);
        $month = $request->date('month', 'Y-m');
        $rows = $payroll->rows($month->year, $month->month);
        AuditLog::record('payroll.exported', null, $data);
        $name = 'payroll-inputs-'.$month->format('Y-m');

        if ($data['format'] === 'json') {
            return response()->json([
                'period' => $month->format('Y-m'),
                'generated_at' => now()->toIso8601String(),
                'library' => 'rdcruz-ph-payroll',
                'employees' => $payroll->computePayInputs($rows, $data['frequency'] ?? 'monthly'),
            ], 200, ['Content-Disposition' => "attachment; filename=\"$name.json\""], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Employee No', 'Name', 'Type', 'Office', 'Paid by agency', 'GSIS member', 'Basis', 'Rate', 'Days present', 'Travel/OB days', 'Leave days',
                'Absences', 'LWOP days', 'Undertime (min)', 'Undertime w/o pay (days)', 'Deductions', 'Monthly basic (computePay)', 'OT credited (h)', 'Notes']);
            foreach ($rows as $r) {
                fputcsv($out, [$r['employee_no'], $r['name'], $r['type_label'], $r['office'], $r['paid_by_agency'] ? 'yes' : 'no', $r['gsis_member'] ? 'yes' : 'no',
                    $r['basis'], $r['rate'], $r['days_present'], $r['travel_days'], $r['leave_days'], $r['days_absent'], $r['lwop_days'], $r['undertime_minutes'],
                    $r['undertime_without_pay_days'] ?? '', $r['deductions'], $r['monthly_basic'], round($r['overtime_credited_minutes'] / 60, 2), implode('; ', $r['notes'])]);
            }
            fclose($out);
        }, "$name.csv", ['Content-Type' => 'text/csv']);
    }
}
