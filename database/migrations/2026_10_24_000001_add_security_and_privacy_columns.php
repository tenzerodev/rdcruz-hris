<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Government ID numbers are stored encrypted (Data Privacy Act: sensitive personal information). */
    private const ENCRYPTED = ['tin', 'gsis_no', 'philhealth_no', 'pagibig_no', 'sss_no'];

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('disabled_at')->nullable();
            $table->boolean('must_change_password')->default(false);
            $table->timestamp('password_changed_at')->nullable();
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
        });

        Schema::table('employees', function (Blueprint $table) {
            foreach (self::ENCRYPTED as $column) {
                $table->text($column)->nullable()->change();
            }
            $table->timestamp('face_purged_at')->nullable();
        });

        DB::table('employees')->orderBy('id')->each(function ($row) {
            $values = [];
            foreach (self::ENCRYPTED as $column) {
                if ($row->{$column} !== null && $row->{$column} !== '') {
                    $values[$column] = Crypt::encryptString($row->{$column});
                }
            }
            if ($values) {
                DB::table('employees')->where('id', $row->id)->update($values);
            }
        });
    }

    public function down(): void
    {
        DB::table('employees')->orderBy('id')->each(function ($row) {
            $values = [];
            foreach (self::ENCRYPTED as $column) {
                if ($row->{$column}) {
                    try {
                        $values[$column] = Crypt::decryptString($row->{$column});
                    } catch (Throwable) {
                        // Already plain text.
                    }
                }
            }
            if ($values) {
                DB::table('employees')->where('id', $row->id)->update($values);
            }
        });

        Schema::table('employees', function (Blueprint $table) {
            foreach (self::ENCRYPTED as $column) {
                $table->string($column, 30)->nullable()->change();
            }
            $table->dropColumn('face_purged_at');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['disabled_at', 'must_change_password', 'password_changed_at', 'two_factor_secret',
                'two_factor_recovery_codes', 'two_factor_confirmed_at', 'last_login_at', 'last_login_ip']);
        });
    }
};
