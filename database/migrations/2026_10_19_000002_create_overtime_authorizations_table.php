<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Approved overtime (office order / overtime authority) covering an employee for a date range. */
    public function up(): void
    {
        Schema::create('overtime_authorizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('date_from');
            $table->date('date_to');
            $table->unsignedSmallInteger('max_minutes_per_day')->nullable(); // null = no daily limit
            $table->string('purpose');
            $table->string('reference_no', 100)->nullable(); // office order / memo no.
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'date_from', 'date_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overtime_authorizations');
    }
};
