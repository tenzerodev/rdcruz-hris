<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\Setting;
use App\Services\LoanService;
use App\Services\PayrollRunService;
use App\Services\RemittanceReport;
use App\Support\Notify;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Payroll runs: generate draft payslips from attendance, adjust them, then release them to employees. */
class PayrollRunController extends Controller
{
    public function __construct(private PayrollRunService $service, private LoanService $loans) {}

    public function index()
    {
        return view('admin.payroll.runs', [
            'runs' => PayrollRun::withCount('payslips')->withSum('payslips', 'net')->orderByDesc('period')->orderByDesc('cut')->paginate(24),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'period' => ['required', 'date_format:Y-m'],
            'cut' => ['required', Rule::in(array_keys(PayrollRun::CUTS))],
            'pay_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:250'],
        ]);
        $bonus = in_array($data['cut'], ['mid_year', 'year_end'], true);
        $exists = $bonus
            ? PayrollRun::where('cut', $data['cut'])->where('period', 'like', substr($data['period'], 0, 4).'-%')->exists()
            : PayrollRun::where('period', $data['period'])->where(fn ($q) => $data['cut'] === 'full'
                ? $q->whereIn('cut', ['full', 'first', 'second']) : $q->whereIn('cut', ['full', $data['cut']]))->exists();
        if ($exists) {
            throw ValidationException::withMessages(['period' => $bonus ? 'That bonus was already made for this year. Open it instead.' : 'There is already a payroll for that period. Open it instead.']);
        }
        @set_time_limit(300);
        $run = PayrollRun::create([...$data, 'created_by' => $request->user()->id]);
        $count = $this->service->generate($run);
        AuditLog::record('payroll.run_created', $run, ['period' => $run->label, 'payslips' => $count]);

        return redirect()->route('admin.payroll.runs.show', $run)->with('status', "Draft payroll made: {$count} payslips. Check them, add loans or other items, then release.");
    }

    public function show(PayrollRun $run)
    {
        $slips = $run->payslips()->with('employee')->get()->sortBy(fn ($s) => $s->employee->last_name.' '.$s->employee->first_name)->values();

        return view('admin.payroll.run', [
            'run' => $run,
            'slips' => $slips,
            'totals' => [
                'gross' => $slips->sum('gross'), 'deductions' => $slips->sum('total_deductions'), 'net' => $slips->sum('net'), 'tax' => $slips->sum('tax'),
                'viewed' => $slips->whereNotNull('viewed_at')->count(),
            ],
        ]);
    }

    /** Re-reads attendance (after DTR corrections or leave posting). Lines added by hand are kept. */
    public function regenerate(PayrollRun $run)
    {
        $this->draftOnly($run);
        @set_time_limit(300);
        $count = $this->service->generate($run);
        AuditLog::record('payroll.run_regenerated', $run);

        return back()->with('status', "Recomputed {$count} payslips from the latest attendance.");
    }

    public function editSlip(Payslip $payslip)
    {
        return view('admin.payroll.slip-edit', ['slip' => $payslip->load('employee', 'run')]);
    }

    public function updateSlip(Request $request, Payslip $payslip)
    {
        $this->draftOnly($payslip->run);
        $data = $request->validate([
            'earnings' => ['array'], 'earnings.*.label' => ['nullable', 'string', 'max:80'], 'earnings.*.amount' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'deductions' => ['array'], 'deductions.*.label' => ['nullable', 'string', 'max:80'], 'deductions.*.amount' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
        ]);
        $earnings = collect($data['earnings'] ?? [])->filter(fn ($l, $i) => filled($l['label'] ?? null) && (float) ($l['amount'] ?? 0) > 0)
            ->map(fn ($l, $i) => ['label' => $l['label'], 'amount' => (float) $l['amount'], 'taxable' => $request->boolean("earnings.$i.taxable")])->values()->all();
        $deductions = collect($data['deductions'] ?? [])->filter(fn ($l) => filled($l['label'] ?? null) && (float) ($l['amount'] ?? 0) > 0)
            ->map(fn ($l) => ['label' => $l['label'], 'amount' => (float) $l['amount']])->values()->all();

        $this->service->compute($payslip, $earnings, $deductions);
        if ($payslip->net < 0) {
            AuditLog::record('payroll.slip_updated', $payslip, ['net' => $payslip->net]);

            return back()->with('warning', 'Saved, but net pay is now negative. Reduce the deductions.');
        }
        AuditLog::record('payroll.slip_updated', $payslip, ['net' => $payslip->net]);

        return redirect()->route('admin.payroll.runs.show', $payslip->run)->with('status', "Updated {$payslip->employee->full_name}: net pay ₱".number_format($payslip->net, 2).'.');
    }

    /** Employees can see their payslips from now on, and are told by email (no amounts in the email). */
    public function release(Request $request, PayrollRun $run)
    {
        $this->draftOnly($run);
        if ($run->payslips()->where('net', '<', 0)->exists()) {
            throw ValidationException::withMessages(['run' => 'Some payslips have negative net pay. Fix them first.']);
        }
        $run->forceFill(['status' => 'released', 'released_at' => now(), 'released_by' => $request->user()->id])->save();
        $this->loans->recordPayments($run->load('payslips'));
        AuditLog::record('payroll.released', $run, ['period' => $run->label]);

        foreach ($run->payslips()->with('employee')->get() as $slip) {
            Notify::employee($slip->employee, 'Your payslip for '.$run->label.' is ready', [
                'Your payslip for '.$run->label.' is now available in the HRIS.',
                'For your privacy, amounts are not included in this email.',
            ], route('me.payslips.show', $slip));
        }

        return back()->with('status', 'Released. Employees can now view their payslips.');
    }

    /** Takes a released payroll back to draft (e.g. a mistake was found). Employees stop seeing it. */
    public function withdraw(PayrollRun $run)
    {
        abort_unless($run->isReleased(), 422);
        $run->forceFill(['status' => 'draft', 'released_at' => null, 'released_by' => null])->save();
        $this->loans->reversePayments($run);
        AuditLog::record('payroll.withdrawn', $run, ['period' => $run->label]);

        return back()->with('status', 'Back to draft. Employees no longer see these payslips until you release again.');
    }

    public function destroy(PayrollRun $run)
    {
        $this->draftOnly($run);
        AuditLog::record('payroll.run_deleted', null, ['period' => $run->label]);
        $run->delete();

        return redirect()->route('admin.payroll.runs.index')->with('status', 'Draft payroll deleted.');
    }

    /** All payslips of the run, for printing and distribution. */
    public function print(PayrollRun $run)
    {
        AuditLog::record('payroll.payslips_printed', $run);

        return view('print.payslip', [
            'slips' => $run->payslips()->with('employee', 'run')->get()->sortBy(fn ($s) => $s->employee->last_name.' '.$s->employee->first_name)->values(),
            'settings' => Setting::allValues(),
            'backUrl' => route('admin.payroll.runs.show', $run),
        ]);
    }

    /** Payroll register as CSV, for accounting and the bank. */
    public function export(PayrollRun $run)
    {
        $slips = $run->payslips()->with('employee')->get()->sortBy(fn ($s) => $s->employee->last_name);
        AuditLog::record('payroll.register_exported', $run);

        return response()->streamDownload(function () use ($slips) {
            $out = fopen('php://output', 'w');
            $labels = $slips->flatMap(fn ($s) => array_column($s->deductions, 'label'))->unique()->values();
            fputcsv($out, ['Employee No', 'Name', 'Position', 'Office', 'Gross', ...$labels, 'Total deductions', 'Net pay']);
            foreach ($slips as $s) {
                $d = collect($s->deductions)->groupBy('label')->map(fn ($g) => $g->sum('amount'));
                fputcsv($out, [$s->employee->employee_no, $s->employee->full_name, $s->employee->position, $s->employee->office, $s->gross,
                    ...$labels->map(fn ($l) => $d[$l] ?? 0), $s->total_deductions, $s->net]);
            }
            fclose($out);
        }, 'payroll-register-'.$run->period.'-'.$run->cut.'.csv', ['Content-Type' => 'text/csv']);
    }

    /** Bank credit list (CSV): account number, name and net pay, for the payroll bank's upload. */
    public function bank(PayrollRun $run, RemittanceReport $report)
    {
        $list = $report->bankList($run->payslips()->with('employee')->where('net', '>', 0)->get());
        $missing = array_filter($list, fn ($r) => ! $r['account']);
        if ($missing) {
            return back()->with('warning', 'No payroll bank account no. for: '.implode(', ', array_column($missing, 'name')).'. Add it on their employee record (Government numbers), then try again.');
        }
        AuditLog::record('payroll.bank_file_exported', $run, ['count' => count($list)]);

        return response()->streamDownload(function () use ($list) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Account Number', 'Account Name', 'Amount', 'Employee No']);
            foreach ($list as $r) {
                fputcsv($out, [$r['account'], $r['name'], number_format($r['amount'], 2, '.', ''), $r['employee_no']]);
            }
            fclose($out);
        }, 'bank-credit-'.$run->period.'-'.$run->cut.'.csv', ['Content-Type' => 'text/csv']);
    }

    private function draftOnly(PayrollRun $run): void
    {
        if ($run->isReleased()) {
            throw ValidationException::withMessages(['run' => 'This payroll is released. Withdraw it to draft before changing it.']);
        }
    }
}
