<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Keeps the IP the activation code was entered from; last_ip changes with every punch. */
    public function up(): void
    {
        Schema::table('issued_devices', function (Blueprint $table) {
            $table->string('activated_ip', 45)->nullable()->after('activated_at');
        });

        // Computers not used since they were registered still hold the registration IP in last_ip.
        DB::table('issued_devices')->whereNotNull('activated_at')->whereNull('last_used_at')->update(['activated_ip' => DB::raw('last_ip')]);
    }

    public function down(): void
    {
        Schema::table('issued_devices', function (Blueprint $table) {
            $table->dropColumn('activated_ip');
        });
    }
};
