<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Vacancies published for hiring (CS Form No. 9), applicants, and HRMPSB assessments.
        Schema::create('vacancies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('position_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 150);
            $table->string('item_no', 60)->nullable();
            $table->unsignedTinyInteger('salary_grade')->nullable();
            $table->decimal('monthly_salary', 12, 2)->nullable();
            $table->string('employment_type', 20)->default('permanent');
            $table->string('place_of_assignment', 150)->nullable();
            $table->text('education')->nullable();
            $table->text('training')->nullable();
            $table->text('experience')->nullable();
            $table->text('eligibility')->nullable();
            $table->text('competencies')->nullable();
            $table->text('instructions')->nullable();
            $table->unsignedSmallInteger('slots')->default(1);
            $table->date('posted_on')->nullable();
            $table->date('closing_on')->nullable();
            $table->string('status', 12)->default('draft'); // draft | open | closed | filled | cancelled
            $table->boolean('public')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('applicants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vacancy_id')->constrained()->cascadeOnDelete();
            $table->string('reference_no', 20)->unique();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete(); // internal applicant
            $table->string('last_name', 80);
            $table->string('first_name', 80);
            $table->string('middle_name', 80)->nullable();
            $table->string('email', 150);
            $table->string('mobile', 30)->nullable();
            $table->string('address', 250)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('sex', 6)->nullable();
            $table->string('current_position', 150)->nullable();
            $table->text('education')->nullable();
            $table->text('training')->nullable();
            $table->text('experience')->nullable();
            $table->text('eligibility')->nullable();
            $table->json('documents')->nullable(); // [{label, name, path}]
            $table->string('status', 15)->default('received');
            $table->string('remarks', 500)->nullable();
            $table->timestamp('consent_at')->nullable();
            $table->timestamp('files_purged_at')->nullable();
            $table->timestamps();
            $table->index(['vacancy_id', 'status']);
        });

        Schema::create('applicant_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->constrained()->cascadeOnDelete();
            $table->string('assessor', 120); // HRMPSB member
            $table->json('scores'); // criterion => points
            $table->decimal('total', 6, 2);
            $table->string('remarks', 500)->nullable();
            $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['applicant_id', 'assessor']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applicant_assessments');
        Schema::dropIfExists('applicants');
        Schema::dropIfExists('vacancies');
    }
};
