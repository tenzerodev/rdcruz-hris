<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // PDS sections not modelled elsewhere (CSC Form 212): family, physical data, addresses,
        // work experience outside this agency, voluntary work, other information, questions 34–40, references.
        Schema::table('employees', function (Blueprint $table) {
            $table->json('pds')->nullable();
            $table->timestamp('pds_updated_at')->nullable();
        });

        Schema::create('performance_periods', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status', 15)->default('planning'); // planning | rating | closed
            $table->timestamps();
        });

        Schema::create('ipcrs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('performance_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rater_id')->nullable()->constrained('employees')->nullOnDelete();
            // draft → targets_submitted → targets_approved → self_rated → rated → final
            $table->string('status', 20)->default('draft');
            $table->decimal('final_rating', 4, 2)->nullable();
            $table->string('adjectival', 30)->nullable();
            $table->text('comments')->nullable();         // rater's comments and recommendations
            $table->text('development_plan')->nullable();
            $table->timestamp('targets_approved_at')->nullable();
            $table->timestamp('rated_at')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();
            $table->unique(['employee_id', 'performance_period_id']);
        });

        Schema::create('ipcr_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ipcr_id')->constrained()->cascadeOnDelete();
            $table->string('category', 10); // strategic | core | support
            $table->string('output');       // MFO / PAP
            $table->text('success_indicator');
            $table->text('accomplishment')->nullable();
            $table->unsignedTinyInteger('self_q')->nullable();
            $table->unsignedTinyInteger('self_e')->nullable();
            $table->unsignedTinyInteger('self_t')->nullable();
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
        Schema::dropIfExists('ipcr_items');
        Schema::dropIfExists('ipcrs');
        Schema::dropIfExists('performance_periods');
        Schema::table('employees', fn (Blueprint $t) => $t->dropColumn(['pds', 'pds_updated_at']));
    }
};
