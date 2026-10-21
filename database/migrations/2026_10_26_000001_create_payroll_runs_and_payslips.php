<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->string('period', 7);              // YYYY-MM
            $table->string('cut', 10)->default('full'); // full (monthly) | first | second (semi-monthly halves)
            $table->date('pay_date')->nullable();
            $table->string('status', 10)->default('draft'); // draft | released
            $table->string('notes', 250)->nullable();
            $table->timestamp('released_at')->nullable();
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['period', 'cut']);
        });

        Schema::create('payslips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->json('inputs');        // attendance and pay basis used (for recomputing and showing)
            $table->json('earnings');      // [{label, amount, taxable, manual}]
            $table->json('deductions');    // [{label, amount, kind, manual}]
            $table->json('employer_share')->nullable();
            $table->decimal('gross', 12, 2)->default(0);
            $table->decimal('total_deductions', 12, 2)->default(0);
            $table->decimal('net', 12, 2)->default(0);
            $table->decimal('taxable', 12, 2)->default(0);
            $table->decimal('tax', 12, 2)->default(0);
            $table->timestamp('viewed_at')->nullable();
            $table->timestamps();
            $table->unique(['payroll_run_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payslips');
        Schema::dropIfExists('payroll_runs');
    }
};
