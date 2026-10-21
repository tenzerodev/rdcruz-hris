<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\Loan;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Loans and recurring deductions, taken from every regular payslip until paid or stopped. */
class LoanController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'active');
        $q = trim((string) $request->query('q'));

        $loans = Loan::with('employee')->withSum('payments', 'amount')
            ->when($status !== 'all', fn ($w) => $w->where('status', $status))
            ->when($request->query('lender'), fn ($w, $l) => $w->where('lender', $l))
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('name', 'like', "%$q%")->orWhere('reference_no', 'like', "%$q%")
                ->orWhereHas('employee', fn ($e) => $e->where('last_name', 'like', "%$q%")->orWhere('first_name', 'like', "%$q%")->orWhere('employee_no', $q))))
            ->latest()->paginate(40)->withQueryString();

        return view('admin.payroll.loans', [
            'loans' => $loans,
            'status' => $status,
            'employees' => Employee::where('is_active', true)->whereIn('employment_type', config('hris.payroll.paid_by_agency'))->orderBy('last_name')->get(),
            'monthly' => Loan::where('status', 'active')->sum('amortization'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $loan = Loan::create($data + ['status' => 'active', 'created_by' => $request->user()->id]);
        AuditLog::record('loan.created', $loan, $data);

        return back()->with('status', "Added {$loan->name} for {$loan->employee->full_name}. It will be deducted from the next draft payroll.");
    }

    public function update(Request $request, Loan $loan)
    {
        if ($request->has('status')) {
            $status = $request->validate(['status' => ['required', Rule::in(['active', 'stopped'])]])['status'];
            if ($status === 'active' && $loan->balance() === 0.0) {
                throw ValidationException::withMessages(['status' => 'This loan is fully paid.']);
            }
            $loan->update(['status' => $status]);
            AuditLog::record('loan.'.($status === 'active' ? 'resumed' : 'stopped'), $loan);

            return back()->with('status', $status === 'active' ? 'Deductions resumed.' : 'Deductions stopped. Payments made so far are kept.');
        }

        $data = $this->validated($request, $loan);
        $loan->update($data);
        AuditLog::record('loan.updated', $loan, $data);

        return back()->with('status', 'Saved. Draft payrolls pick up the change when recomputed.');
    }

    public function destroy(Loan $loan)
    {
        if ($loan->payments()->exists()) {
            throw ValidationException::withMessages(['loan' => 'Payments were already made on this loan. Stop it instead, so the history is kept.']);
        }
        AuditLog::record('loan.deleted', $loan, $loan->only('employee_id', 'name', 'amortization'));
        $loan->delete();

        return back()->with('status', 'Deleted.');
    }

    private function validated(Request $request, ?Loan $loan = null): array
    {
        $data = $request->validate([
            'employee_id' => [$loan ? 'prohibited' : 'required', 'exists:employees,id'],
            'lender' => ['required', Rule::in(array_keys(Loan::lenders()))],
            'kind' => ['required', Rule::in(array_keys(Loan::KINDS))],
            'name' => ['required', 'string', 'max:120'],
            'reference_no' => ['nullable', 'string', 'max:60'],
            'total_amount' => ['nullable', 'required_if:kind,loan', 'numeric', 'min:1', 'max:99999999'],
            'amortization' => ['required', 'numeric', 'min:1', 'max:9999999'],
            'start_period' => ['required', 'date_format:Y-m'],
            'end_period' => ['nullable', 'date_format:Y-m', 'after_or_equal:start_period'],
            'notes' => ['nullable', 'string', 'max:250'],
        ], ['total_amount.required_if' => 'Enter the total amount payable for a loan.']);
        if ($data['kind'] === 'contribution') {
            $data['total_amount'] = null;
        }
        if ($loan && $data['total_amount'] !== null && $data['total_amount'] < $loan->paid()) {
            throw ValidationException::withMessages(['total_amount' => 'The total is less than what was already paid (₱'.number_format($loan->paid(), 2).').']);
        }

        return $data;
    }
}
