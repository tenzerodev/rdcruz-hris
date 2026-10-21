<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** One row per printed document version, checked by anyone at /verify/{code}. */
    public function up(): void
    {
        Schema::create('document_verifications', function (Blueprint $table) {
            $table->id();
            $table->string('code', 16)->unique();
            $table->string('type', 30);                 // form48 | form6 | service_record | coe | travel_order | ipcr | pds | overtime_report
            $table->string('subject_key', 100);         // which document: e.g. "form48:12:2026-10"
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->json('summary');                    // the facts shown publicly to compare with the paper
            $table->string('fingerprint', 64);          // sha256 of the summary: same content → same code
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoked_reason')->nullable();
            $table->unsignedInteger('views')->default(0);
            $table->timestamp('last_viewed_at')->nullable();
            $table->timestamps();
            $table->index(['subject_key', 'fingerprint']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_verifications');
    }
};
