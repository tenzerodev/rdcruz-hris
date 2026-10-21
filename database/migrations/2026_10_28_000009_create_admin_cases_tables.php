<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Administrative cases (complaints, investigation, formal charge, hearings, decision), confidential to HR.
        Schema::create('admin_cases', function (Blueprint $table) {
            $table->id();
            $table->string('case_no', 40)->unique();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete(); // respondent
            $table->string('complainant', 200)->nullable();
            $table->string('offense', 200);
            $table->string('classification', 12)->nullable(); // grave | less_grave | light
            $table->text('description')->nullable();
            $table->date('date_filed');
            $table->string('status', 25)->default('complaint');
            $table->string('penalty', 20)->nullable();
            $table->text('decision')->nullable();
            $table->date('decided_on')->nullable();
            $table->date('closed_on')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['employee_id', 'status']);
        });
        Schema::create('case_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_case_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('type', 20);
            $table->text('details')->nullable();
            $table->date('due_on')->nullable(); // e.g. answer due in 5 days
            $table->string('document_path')->nullable();
            $table->string('document_name')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        // Preventive or penalty suspension: shown on the DTR and deducted from pay.
        Schema::create('case_suspensions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 12); // preventive | penalty
            $table->date('date_from');
            $table->date('date_to');
            $table->boolean('with_pay')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_suspensions');
        Schema::dropIfExists('case_events');
        Schema::dropIfExists('admin_cases');
    }
};
