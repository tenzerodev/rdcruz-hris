<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SPMS office and division commitments: OPCR (office) and DPCR (division).
        Schema::create('office_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('performance_period_id')->constrained()->cascadeOnDelete();
            $table->string('level', 4); // opcr | dpcr
            $table->string('office', 150);
            $table->foreignId('head_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('status', 20)->default('draft'); // draft | targets_approved | rated | final
            $table->decimal('final_rating', 4, 2)->nullable();
            $table->string('adjectival', 30)->nullable();
            $table->text('comments')->nullable();
            $table->timestamp('targets_approved_at')->nullable();
            $table->timestamp('rated_at')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();
            $table->unique(['performance_period_id', 'level', 'office']);
        });
        Schema::create('office_rating_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('office_rating_id')->constrained()->cascadeOnDelete();
            $table->string('category', 10);
            $table->string('output');
            $table->text('success_indicator');
            $table->decimal('budget', 14, 2)->nullable();
            $table->string('accountable', 200)->nullable(); // divisions / individuals accountable
            $table->text('accomplishment')->nullable();
            $table->unsignedTinyInteger('q')->nullable();
            $table->unsignedTinyInteger('e')->nullable();
            $table->unsignedTinyInteger('t')->nullable();
            $table->string('remarks')->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('office_rating_items');
        Schema::dropIfExists('office_ratings');
    }
};
