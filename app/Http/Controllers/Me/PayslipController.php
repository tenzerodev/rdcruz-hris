<?php

namespace App\Http\Controllers\Me;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Payslip;
use App\Models\Setting;
use Illuminate\Http\Request;

class PayslipController extends Controller
{
    public function index(Request $request)
    {
        $e = $request->user()->employee;
        $slips = $e->payslips()->with('run')->whereHas('run', fn ($q) => $q->where('status', 'released'))->get()
            ->sortByDesc(fn ($s) => $s->run->period.$s->run->cut)->values();
        $year = now()->year;
        $ytd = $slips->filter(fn ($s) => str_starts_with($s->run->period, (string) $year));

        return view('me.payslips', [
            'slips' => $slips,
            'year' => $year,
            'ytd' => ['gross' => $ytd->sum('gross'), 'tax' => $ytd->sum('tax'), 'net' => $ytd->sum('net')],
            'loans' => $e->loans()->withSum('payments', 'amount')->where(fn ($q) => $q->where('status', 'active')->orWhere('updated_at', '>=', now()->subYear()))->get(),
        ]);
    }

    /** One payslip, printable. The employee and payroll staff only — supervisors don't see pay. */
    public function show(Request $request, Payslip $payslip)
    {
        $payslip->load('run', 'employee');
        abort_unless($payslip->canBeSeenBy($request->user()), 404);

        if ($request->user()->employee?->id === $payslip->employee_id && ! $payslip->viewed_at) {
            $payslip->forceFill(['viewed_at' => now()])->saveQuietly();
        }
        AuditLog::record('payslip.viewed', $payslip);

        return view('print.payslip', [
            'slips' => collect([$payslip]),
            'settings' => Setting::allValues(),
            'backUrl' => $request->user()->employee?->id === $payslip->employee_id ? route('me.payslips') : route('admin.payroll.runs.show', $payslip->run),
        ]);
    }
}
