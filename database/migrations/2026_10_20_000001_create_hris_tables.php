<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->string('item_no', 60)->unique();
            $table->string('title');
            $table->unsignedTinyInteger('salary_grade')->nullable();
            $table->string('office')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->string('employment_type', 30)->default('permanent')->after('employee_no');
            $table->string('schedule_type', 10)->default('flexi')->after('employment_type'); // flexi | fixed
            $table->foreignId('supervisor_id')->nullable()->after('supervisor_name')->constrained('employees')->nullOnDelete();
            $table->foreignId('position_id')->nullable()->unique()->after('position')->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('salary_grade')->nullable();
            $table->unsignedTinyInteger('step')->nullable();
            $table->decimal('monthly_salary', 12, 2)->nullable();
            $table->decimal('daily_rate', 10, 2)->nullable(); // COS / JO
            $table->date('contract_start')->nullable();
            $table->date('contract_end')->nullable();
            $table->string('agency_name')->nullable(); // service provider company, or DBP office
            $table->string('agency_contract_no', 100)->nullable();
            $table->string('sex', 10)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('civil_status', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('mobile', 30)->nullable();
            $table->string('address')->nullable();
            $table->string('tin', 30)->nullable();
            $table->string('gsis_no', 30)->nullable();
            $table->string('philhealth_no', 30)->nullable();
            $table->string('pagibig_no', 30)->nullable();
            $table->string('sss_no', 30)->nullable();
            $table->date('date_hired')->nullable();
            $table->date('date_separated')->nullable();
            $table->string('separation_reason')->nullable();
            $table->string('emergency_contact')->nullable();
            $table->string('emergency_phone', 30)->nullable();
        });

        Schema::create('service_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('date_from');
            $table->date('date_to')->nullable(); // null = present
            $table->string('designation');
            $table->string('status', 50);
            $table->decimal('annual_salary', 12, 2)->nullable();
            $table->string('office')->nullable();
            $table->string('branch')->nullable();
            $table->string('leave_without_pay')->nullable();
            $table->string('separation')->nullable(); // date / cause
            $table->string('remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('educations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('level', 20); // elementary | secondary | vocational | college | graduate
            $table->string('school');
            $table->string('degree')->nullable();
            $table->string('year_from', 4)->nullable();
            $table->string('year_to', 4)->nullable();
            $table->string('year_graduated', 4)->nullable();
            $table->string('honors')->nullable();
            $table->timestamps();
        });

        Schema::create('eligibilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('rating', 20)->nullable();
            $table->date('exam_date')->nullable();
            $table->string('exam_place')->nullable();
            $table->string('license_no', 60)->nullable();
            $table->date('valid_until')->nullable();
            $table->timestamps();
        });

        Schema::create('trainings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->date('date_from');
            $table->date('date_to')->nullable();
            $table->unsignedSmallInteger('hours')->nullable();
            $table->string('type', 30)->nullable(); // managerial | supervisory | technical | foundation
            $table->string('sponsor')->nullable();
            $table->timestamps();
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('category', 40);
            $table->string('title');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 100)->nullable();
            $table->unsignedInteger('size')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('leave_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10);
            $table->date('date_from');
            $table->date('date_to');
            $table->string('half_day', 2)->nullable(); // am | pm (single day only)
            $table->decimal('days', 6, 2);
            $table->json('details')->nullable(); // where spent, illness, etc. (Form 6 section 6.B)
            $table->boolean('commutation')->default(false);
            $table->string('reason')->nullable();
            $table->string('status', 15)->default('pending'); // pending | recommended | approved | disapproved | cancelled
            $table->foreignId('filed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('recommended_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('recommended_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->decimal('days_with_pay', 6, 2)->nullable();
            $table->decimal('days_without_pay', 6, 2)->nullable();
            $table->string('remarks')->nullable();
            $table->timestamps();
            $table->index(['employee_id', 'status', 'date_from']);
        });

        Schema::create('leave_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('balance', 5); // VL | SL | SPL | COC
            $table->date('entry_date');
            $table->string('period', 20)->nullable(); // e.g. 2026-10, used to keep postings idempotent
            $table->string('particulars');
            $table->decimal('earned', 9, 3)->default(0);
            $table->decimal('used', 9, 3)->default(0);
            $table->string('source', 20); // accrual | leave | undertime | coc | adjustment | lapse | forced
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['employee_id', 'balance', 'entry_date']);
            $table->index(['source', 'period']);
        });

        Schema::create('travel_orders', function (Blueprint $table) {
            $table->id();
            $table->string('type', 15); // ob | travel | training | wfh
            $table->date('date_from');
            $table->date('date_to');
            $table->string('time_from', 5)->nullable(); // partial-day OB
            $table->string('time_to', 5)->nullable();
            $table->string('destination');
            $table->string('purpose');
            $table->string('reference_no', 60)->nullable();
            $table->string('status', 15)->default('pending'); // pending | approved | disapproved | cancelled
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('employee_travel_order', function (Blueprint $table) {
            $table->foreignId('travel_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->primary(['travel_order_id', 'employee_id']);
        });

        Schema::table('overtime_authorizations', function (Blueprint $table) {
            $table->string('status', 15)->default('approved')->after('reference_no'); // pending | approved | disapproved
            $table->foreignId('requested_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable()->after('approved_by');
            $table->string('remarks')->nullable()->after('decided_at');
        });

        Schema::table('holidays', function (Blueprint $table) {
            $table->string('type', 15)->default('regular')->after('name'); // regular | special | suspension
        });
    }

    public function down(): void
    {
        Schema::table('holidays', fn (Blueprint $t) => $t->dropColumn('type'));
        Schema::table('overtime_authorizations', function (Blueprint $t) {
            $t->dropConstrainedForeignId('requested_by');
            $t->dropColumn(['status', 'decided_at', 'remarks']);
        });
        foreach (['employee_travel_order', 'travel_orders', 'leave_ledger', 'leave_applications', 'documents', 'trainings', 'eligibilities', 'educations', 'service_records'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::table('employees', function (Blueprint $t) {
            $t->dropConstrainedForeignId('supervisor_id');
            $t->dropConstrainedForeignId('position_id');
            $t->dropColumn(['employment_type', 'schedule_type', 'salary_grade', 'step', 'monthly_salary', 'daily_rate', 'contract_start', 'contract_end',
                'agency_name', 'agency_contract_no', 'sex', 'birth_date', 'civil_status', 'email', 'mobile', 'address', 'tin', 'gsis_no',
                'philhealth_no', 'pagibig_no', 'sss_no', 'date_hired', 'date_separated', 'separation_reason', 'emergency_contact', 'emergency_phone']);
        });
        Schema::dropIfExists('positions');
    }
};
