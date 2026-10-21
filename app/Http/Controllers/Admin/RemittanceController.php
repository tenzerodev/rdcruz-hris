<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PayrollRun;
use App\Services\RemittanceReport;
use Illuminate\Http\Request;

/** Monthly remittances to GSIS, PhilHealth, Pag-IBIG, BIR and other lenders, from released payrolls. */
class RemittanceController extends Controller
{
    public function index(Request $request, RemittanceReport $report)
    {
        $period = $this->period($request);
        $runs = PayrollRun::where('period', $period)->get();

        return view('admin.payroll.remittances', [
            'period' => $period,
            'month' => $runs->first()?->month() ?? now()->createFromFormat('Y-m-d', $period.'-01'),
            'sections' => $report->build($period),
            'drafts' => $runs->where('status', 'draft')->count(),
        ]);
    }

    public function export(Request $request, RemittanceReport $report, string $section)
    {
        $period = $this->period($request);
        $data = $report->build($period)[$section] ?? abort(404);
        AuditLog::record('payroll.remittance_exported', null, ['period' => $period, 'section' => $section]);

        return response()->streamDownload(function () use ($data) {
            $out = fopen('php://output', 'w');
            fputcsv($out, array_values(array_filter(['Employee No', 'Name', $data['id_label'], ...$data['columns']])));
            foreach ($data['rows'] as $r) {
                fputcsv($out, array_values(array_filter([$r['employee_no'], $r['name'], $data['id_label'] ? (string) $r['id'] : false, ...$r['values']], fn ($v) => $v !== false)));
            }
            fputcsv($out, array_values(array_filter(['', 'TOTAL', $data['id_label'] ? '' : false, ...$data['totals']], fn ($v) => $v !== false)));
            fclose($out);
        }, "remittance-{$section}-{$period}.csv", ['Content-Type' => 'text/csv']);
    }

    private function period(Request $request): string
    {
        $p = (string) $request->query('period', now()->format('Y-m'));

        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $p) ? $p : now()->format('Y-m');
    }
}
