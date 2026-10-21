<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Memos, office orders, circulars and announcements, with "I have read this" acknowledgments.
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 20);
            $table->string('reference_no', 60)->nullable();
            $table->string('title', 200);
            $table->text('body');
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->json('audience')->nullable(); // {offices: [], groups: []}; null = everyone
            $table->boolean('requires_ack')->default(true);
            $table->boolean('pinned')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->date('expires_on')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('announcement_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();
            $table->unique(['announcement_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_reads');
        Schema::dropIfExists('announcements');
    }
};
