<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Loan;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoanRemittanceTest extends TestCase
{
    use RefreshDatabase;

    private User $payroll;

    private Employee $ana;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-05 09:00:00');
        Setting::put(['session_idle_minutes' => '0', 'attendance_start_date' => '2026-11-01']);
        $this->payroll = User::create(['name' => 'Pay', 'username' => 'pay', 'password' => 'secret123', 'role' => 'payroll']);
        $this->ana = Employee::create(['employee_no' => 'E1', 'last_name' => 'Santos', 'first_name' => 'Ana', 'employment_type' => 'permanent',
            'monthly_salary' => 52000, 'gsis_no' => '0012345', 'tin' => '123-456-789', 'bank_account_no' => '1234-5678-90']);
    }

    private function payrollFor(string $period, string $cut = 'full'): PayrollRun
    {
        $this->actingAs($this->payroll)->post('/admin/payroll/runs', ['period' => $period, 'cut' => $cut])->assertSessionHasNoErrors();

        return PayrollRun::where('period', $period)->where('cut', $cut)->sole();
    }

    public function test_loans_are_deducted_until_paid_and_payments_follow_release(): void
    {
        $this->actingAs($this->payroll)->post('/admin/payroll/loans', [
            'employee_id' => $this->ana->id, 'lender' => 'gsis', 'kind' => 'loan', 'name' => 'GSIS Consolidated Loan', 'reference_no' => 'CL1',
            'total_amount' => 5000, 'amortization' => 3000, 'start_period' => '2026-10',
        ])->assertSessionHasNoErrors();
        $loan = Loan::sole();

        $oct = $this->payrollFor('2026-10');
        $slip = Payslip::where('payroll_run_id', $oct->id)->sole();
        $this->assertEquals(3000.0, collect($slip->deductions)->firstWhere('kind', 'loan')['amount']);
        $this->assertSame(43447.6 - 3000, $slip->net);

        // Released: the payment is recorded. Withdrawn: it is undone.
        $this->post("/admin/payroll/runs/{$oct->id}/release");
        $this->assertSame(2000.0, $loan->fresh()->balance());
        $this->post("/admin/payroll/runs/{$oct->id}/withdraw");
        $this->assertSame(5000.0, $loan->fresh()->balance());
        $this->post("/admin/payroll/runs/{$oct->id}/release");

        // November semi-monthly: half each pay day, and never more than what is left.
        $first = $this->payrollFor('2026-11', 'first');
        $this->assertEquals(1500.0, collect(Payslip::where('payroll_run_id', $first->id)->sole()->deductions)->firstWhere('kind', 'loan')['amount']);
        $this->post("/admin/payroll/runs/{$first->id}/release");
        $second = $this->payrollFor('2026-11', 'second');
        $this->assertEquals(500.0, collect(Payslip::where('payroll_run_id', $second->id)->sole()->deductions)->firstWhere('kind', 'loan')['amount']);
        $this->post("/admin/payroll/runs/{$second->id}/release");
        $this->assertSame('paid', $loan->fresh()->status);

        // Paid loans can't be deleted, and are no longer deducted.
        $this->delete("/admin/payroll/loans/{$loan->id}")->assertSessionHasErrors('loan');
        $this->assertSame(0.0, $loan->fresh()->balance());
    }

    public function test_remittance_lists_and_bank_file(): void
    {
        Loan::create(['employee_id' => $this->ana->id, 'lender' => 'pagibig', 'kind' => 'contribution', 'name' => 'Pag-IBIG MP2 Savings',
            'amortization' => 500, 'start_period' => '2026-10', 'status' => 'active']);
        $run = $this->payrollFor('2026-10');

        $this->get('/admin/payroll/remittances?period=2026-10')->assertOk()->assertSee('No released payroll')->assertSee('still drafts');
        $this->post("/admin/payroll/runs/{$run->id}/release");

        $this->get('/admin/payroll/remittances?period=2026-10')->assertOk()
            ->assertSee('GSIS')->assertSee('0012345')->assertSee('4,680.00')->assertSee('6,240.00') // 9% / 12% of 52,000
            ->assertSee('Pag-IBIG MP2 Savings')->assertSee('123-456-789');
        $csv = $this->get('/admin/payroll/remittances/pagibig?period=2026-10')->assertOk()->streamedContent();
        $this->assertStringContainsString('Pag-IBIG MP2 Savings', $csv);
        $this->assertStringContainsString('SANTOS', mb_strtoupper($csv));
        $this->get('/admin/payroll/remittances/nope?period=2026-10')->assertNotFound();

        $bank = $this->get("/admin/payroll/runs/{$run->id}/bank")->assertOk()->streamedContent();
        $this->assertStringContainsString('1234567890', $bank);
        $this->assertStringContainsString('42947.60', $bank);

        $this->ana->update(['bank_account_no' => null]);
        $this->get("/admin/payroll/runs/{$run->id}/bank")->assertRedirect()->assertSessionHas('warning');
    }

    public function test_employee_sees_own_loans_and_hr_cannot_manage_them(): void
    {
        $user = User::create(['name' => 'Ana', 'username' => 'ana', 'password' => 'secret123', 'role' => 'employee']);
        $this->ana->update(['user_id' => $user->id]);
        Loan::create(['employee_id' => $this->ana->id, 'lender' => 'coop', 'kind' => 'loan', 'name' => 'Coop Loan', 'total_amount' => 10000,
            'amortization' => 1000, 'start_period' => '2026-10', 'status' => 'active']);

        $this->actingAs($user)->get('/me/payslips')->assertOk()->assertSee('Coop Loan')->assertSee('10,000.00');
        $hr = User::create(['name' => 'HR', 'username' => 'hr', 'password' => 'secret123', 'role' => 'hr']);
        $this->actingAs($hr)->get('/admin/payroll/loans')->assertOk()->assertSee('Read-only');
        $this->post('/admin/payroll/loans', [])->assertForbidden();
    }
}
