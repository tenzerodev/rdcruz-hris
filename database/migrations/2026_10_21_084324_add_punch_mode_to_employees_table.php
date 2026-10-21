<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // four = AM and PM in/out, two = one time in and one time out a day. Null = agency default.
            $table->string('punch_mode', 10)->nullable()->after('schedule_type');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('punch_mode');
        });
    }
};
