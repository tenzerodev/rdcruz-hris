<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Learning and development: yearly needs survey (LDNA), the L&D plan, and who attended.
        Schema::create('training_programs', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->string('title', 200);
            $table->string('competency', 200)->nullable();
            $table->string('type', 30)->nullable(); // Training::TYPES
            $table->string('mode', 20)->default('in_house'); // in_house | external | online
            $table->string('provider', 200)->nullable();
            $table->date('date_from')->nullable();
            $table->date('date_to')->nullable();
            $table->unsignedTinyInteger('quarter')->nullable();
            $table->unsignedSmallInteger('hours')->nullable();
            $table->unsignedSmallInteger('slots')->nullable();
            $table->decimal('budget', 12, 2)->nullable();
            $table->decimal('actual_cost', 12, 2)->nullable();
            $table->string('status', 12)->default('planned'); // planned | scheduled | done | cancelled
            $table->string('notes', 500)->nullable();
            $table->timestamps();
            $table->index(['year', 'status']);
        });
        Schema::create('training_needs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->string('competency', 200);
            $table->string('training_title', 200)->nullable();
            $table->string('reason', 500)->nullable();
            $table->string('priority', 8)->default('medium');
            $table->foreignId('training_program_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('training_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_program_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('status', 15)->default('nominated'); // nominated | attended | absent
            $table->foreignId('training_id')->nullable()->constrained()->nullOnDelete(); // the 201 / PDS L&D entry
            $table->timestamps();
            $table->unique(['training_program_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_participants');
        Schema::dropIfExists('training_needs');
        Schema::dropIfExists('training_programs');
    }
};
