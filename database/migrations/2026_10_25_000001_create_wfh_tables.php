<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Work-from-home arrangements set by HR (flexible work arrangement, per office order).
        Schema::create('wfh_arrangements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('date_from');
            $table->date('date_to');
            $table->json('weekdays')->nullable(); // ISO days 1=Mon … 6=Sat; null = every work day in the range
            $table->boolean('require_device')->default(true); // punches only from an issued computer
            $table->string('reference_no', 100)->nullable();
            $table->string('notes', 250)->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['employee_id', 'date_from', 'date_to']);
        });

        // Agency-issued laptops / computers registered for an employee's punching.
        Schema::create('issued_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('property_no', 100)->nullable();
            $table->string('serial_no', 100)->nullable();
            $table->string('token_hash', 64)->nullable();
            $table->string('activation_hash', 64)->nullable();
            $table->timestamp('activation_expires_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->string('last_ip', 45)->nullable();
            $table->string('user_agent', 250)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Daily accomplishment report for WFH days.
        Schema::create('wfh_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('work_date');
            $table->text('accomplishments');
            $table->timestamps();
            $table->unique(['employee_id', 'work_date']);
        });

        Schema::table('punches', function (Blueprint $table) {
            $table->foreignId('issued_device_id')->nullable()->after('kiosk_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('punches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('issued_device_id');
        });
        Schema::dropIfExists('wfh_reports');
        Schema::dropIfExists('issued_devices');
        Schema::dropIfExists('wfh_arrangements');
    }
};
