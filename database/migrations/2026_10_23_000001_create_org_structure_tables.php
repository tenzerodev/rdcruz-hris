<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A version of the organization: the approved structure, or an interim one for a period.
        Schema::create('org_structures', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('kind', 10)->default('approved');    // approved | interim
            $table->string('status', 10)->default('draft');     // draft | active | archived
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();          // interim: when it ends
            $table->string('reference')->nullable();           // board resolution / memo / DBM approval
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('org_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('org_structure_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('org_units')->cascadeOnDelete();
            $table->string('level', 12);                       // office | department | division | unit
            $table->string('code', 30)->nullable();
            $table->string('name');
            $table->foreignId('head_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('head_title', 100)->nullable();     // Department Manager, Division Chief, OIC…
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
            $table->unique(['org_structure_id', 'code']);
        });

        Schema::create('org_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('org_structure_id')->constrained()->cascadeOnDelete();
            $table->foreignId('org_unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('designation')->nullable();         // position in this structure, if different
            $table->timestamps();
            $table->unique(['org_structure_id', 'employee_id']); // one unit per person per structure
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('org_assignments');
        Schema::dropIfExists('org_units');
        Schema::dropIfExists('org_structures');
    }
};
