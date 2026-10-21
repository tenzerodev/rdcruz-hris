<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Loans and other recurring deductions (GSIS, Pag-IBIG, cooperative, MP2, union dues...).
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('lender', 20); // gsis | pagibig | coop | bank | other
            $table->string('kind', 15)->default('loan'); // loan (has a total) | contribution (no end)
            $table->string('name', 120);
            $table->string('reference_no', 60)->nullable();
            $table->decimal('total_amount', 12, 2)->nullable(); // total payable; null for contributions
            $table->decimal('amortization', 12, 2); // per month
            $table->string('start_period', 7); // Y-m
            $table->string('end_period', 7)->nullable();
            $table->string('status', 10)->default('active'); // active | stopped | paid
            $table->string('notes', 250)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['employee_id', 'status']);
        });

        // One line per loan per released payslip.
        Schema::create('loan_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payslip_id')->constrained()->cascadeOnDelete();
            $table->string('period', 7);
            $table->decimal('amount', 12, 2);
            $table->timestamps();
            $table->unique(['loan_id', 'payslip_id']);
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->text('bank_account_no')->nullable(); // encrypted
        });
    }

    public function down(): void
    {
        Schema::table('employees', fn (Blueprint $table) => $table->dropColumn('bank_account_no'));
        Schema::dropIfExists('loan_payments');
        Schema::dropIfExists('loans');
    }
};
