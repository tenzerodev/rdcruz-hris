<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\Setting;
use App\Services\AnnualTax;
use Illuminate\Http\Request;

/** Year-end tax adjustment and BIR Form 2316 figures from released payslips. */
class TaxController extends Controller
{
    public function index(Request $request, AnnualTax $tax)
    {
        $year = $request->integer('year') ?: now()->year;
        $rows = $tax->forYear($year);

        if ($request->input('format') === 'csv') {
            AuditLog::record('tax.alphalist_exported', null, ['year' => $year]);

            return response()->streamDownload(function () use ($rows) {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['Employee No', 'Name', 'TIN', 'Gross compensation', '13th month & other benefits', 'Exempt benefits', 'Other non-taxable (PERA, de minimis)',
                    'GSIS/PhilHealth/Pag-IBIG (employee)', 'Taxable compensation', 'Tax due', 'Tax withheld', 'To collect (+) / refund (−)']);
                foreach ($rows as $r) {
                    fputcsv($out, [$r['employee']->employee_no, $r['employee']->full_name, $r['employee']->tin, $r['gross'], $r['benefits'], $r['benefits_exempt'],
                        $r['other_non_taxable'], $r['contributions'], $r['taxable'], $r['tax_due'], $r['withheld'], $r['difference']]);
                }
                fclose($out);
            }, "annual-tax-{$year}.csv", ['Content-Type' => 'text/csv']);
        }

        return view('admin.payroll.tax', ['rows' => $rows, 'year' => $year]);
    }

    /** BIR Form 2316 data sheet for one employee, or everyone (?all=1). */
    public function certificate(Request $request, AnnualTax $tax, ?Employee $employee = null)
    {
        $year = $request->integer('year') ?: now()->year;
        $rows = $employee ? collect([['employee' => $employee] + $tax->forEmployee($employee, $year)]) : $tax->forYear($year);
        $user = $request->user();
        abort_unless($user->canSee('payroll') || ($employee && $user->employee?->id === $employee->id), 404);
        AuditLog::record('tax.certificate_viewed', $employee, ['year' => $year]);

        return view('print.tax-2316', ['rows' => $rows, 'year' => $year, 'settings' => Setting::allValues()]);
    }

    /** The signed-in employee's own certificate. */
    public function mine(Request $request, AnnualTax $tax)
    {
        return $this->certificate($request, $tax, $request->user()->employee);
    }
}
