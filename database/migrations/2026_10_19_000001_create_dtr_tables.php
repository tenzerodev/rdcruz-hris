<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('employee_no', 50)->unique();
            $table->string('last_name');
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('suffix', 20)->nullable();
            $table->string('position')->nullable();
            $table->string('office')->nullable();
            $table->string('supervisor_name')->nullable(); // "In Charge" on Form 48
            // Optional per-employee official hours (HH:MM). Null = use global settings.
            $table->string('am_in', 5)->nullable();
            $table->string('am_out', 5)->nullable();
            $table->string('pm_in', 5)->nullable();
            $table->string('pm_out', 5)->nullable();
            $table->boolean('allow_personal')->default(true); // may punch from own device
            $table->boolean('is_active')->default(true);
            $table->timestamp('consent_at')->nullable(); // Data Privacy Act consent to face processing
            $table->timestamps();
        });

        Schema::create('face_samples', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->json('descriptor'); // 128 floats from face-api.js; no photo stored
            $table->float('score')->nullable();
            $table->timestamps();
        });

        Schema::create('kiosks', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('location')->nullable();
            $table->string('token_hash', 64)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });

        Schema::create('punches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('work_date');
            $table->string('type', 10); // am_in | am_out | pm_in | pm_out
            $table->dateTime('punched_at');
            $table->string('source', 10); // kiosk | personal | manual
            $table->foreignId('kiosk_id')->nullable()->constrained()->nullOnDelete();
            $table->float('distance')->nullable();
            $table->boolean('liveness')->default(false);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->float('accuracy')->nullable();
            $table->string('snapshot_path')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('remarks')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'work_date', 'type']);
            $table->index('work_date');
        });

        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 50);
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('details')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('holidays');
        Schema::dropIfExists('punches');
        Schema::dropIfExists('kiosks');
        Schema::dropIfExists('face_samples');
        Schema::dropIfExists('employees');
    }
};
