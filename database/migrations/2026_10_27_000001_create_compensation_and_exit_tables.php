<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Salary schedules (SSL tranches or the GOCC's own compensation plan): one amount per grade and step.
        Schema::create('salary_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->date('effective_date');
            $table->string('basis', 250)->nullable();
            $table->timestamps();
        });
        Schema::create('salary_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salary_schedule_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('grade');
            $table->unsignedTinyInteger('step');
            $table->decimal('amount', 12, 2);
            $table->unique(['salary_schedule_id', 'grade', 'step']);
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->date('step_date')->nullable(); // when the current step took effect (appointment or last increment)
        });

        // Notices of Step Increment (NOSI) and Salary Adjustment (NOSA).
        Schema::create('salary_notices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('type', 5); // nosi | nosa
            $table->date('effective_date');
            $table->unsignedTinyInteger('old_grade')->nullable();
            $table->unsignedTinyInteger('old_step')->nullable();
            $table->decimal('old_salary', 12, 2)->nullable();
            $table->unsignedTinyInteger('new_grade')->nullable();
            $table->unsignedTinyInteger('new_step')->nullable();
            $table->decimal('new_salary', 12, 2);
            $table->string('basis', 250)->nullable();
            $table->foreignId('salary_schedule_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Leave monetization (regular / special) and terminal leave benefit computations.
        Schema::create('leave_monetizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 10); // regular | special | terminal
            $table->date('date');
            $table->decimal('vl_days', 9, 3)->default(0);
            $table->decimal('sl_days', 9, 3)->default(0);
            $table->decimal('salary', 12, 2);
            $table->decimal('factor', 10, 7);
            $table->decimal('amount', 12, 2);
            $table->string('reason', 250)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Exit clearance: one sign-off per office.
        Schema::create('clearances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('separation_date');
            $table->string('reason', 150);
            $table->string('status', 10)->default('open'); // open | completed | cancelled
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('clearance_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clearance_id')->constrained()->cascadeOnDelete();
            $table->string('office', 100);
            $table->string('checks', 250)->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamp('cleared_at')->nullable();
            $table->string('cleared_by', 150)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('remarks', 250)->nullable();
            $table->timestamps();
        });

        Schema::create('loyalty_awards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('years');
            $table->date('milestone_date');
            $table->decimal('amount', 12, 2)->default(0);
            $table->timestamp('awarded_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['employee_id', 'years']);
        });

        // SALN (Statement of Assets, Liabilities and Net Worth) filing, as of December 31 of the year.
        Schema::create('salns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->date('filed_on');
            $table->string('filing', 10)->default('joint'); // joint | separate | not_applicable
            $table->string('remarks', 250)->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['employee_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salns');
        Schema::dropIfExists('loyalty_awards');
        Schema::dropIfExists('clearance_items');
        Schema::dropIfExists('clearances');
        Schema::dropIfExists('leave_monetizations');
        Schema::dropIfExists('salary_notices');
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('step_date');
        });
        Schema::dropIfExists('salary_rates');
        Schema::dropIfExists('salary_schedules');
    }
};
