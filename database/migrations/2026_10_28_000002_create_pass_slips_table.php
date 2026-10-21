<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pass slips / locator slips: short trips out of the office during work hours.
        Schema::create('pass_slips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('type', 10); // official | personal
            $table->string('time_out', 5);
            $table->string('time_back', 5)->nullable(); // null = not coming back today
            $table->string('destination', 200);
            $table->string('purpose', 250);
            $table->string('status', 12)->default('pending');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('remarks', 250)->nullable();
            $table->string('actual_out', 5)->nullable();
            $table->string('actual_back', 5)->nullable();
            $table->timestamps();
            $table->index(['employee_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pass_slips');
    }
};
