<?php

namespace Database\Seeders;

use App\Models\Holiday;
use App\Models\User;
use App\Support\PhHolidays;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! User::where('username', 'admin')->exists()) {
            $password = env('DTR_ADMIN_PASSWORD') ?: Str::password(14, symbols: false);
            User::create(['name' => 'Administrator', 'username' => 'admin', 'role' => 'admin', 'password' => $password]);
            $this->command?->warn("Admin login → username: admin  password: {$password}  (change it after logging in)");
        }

        // Philippine regular holidays this year and next (check the yearly proclamation and add special days).
        foreach ([now()->year, now()->year + 1] as $year) {
            foreach (PhHolidays::regular($year) as $date => $name) {
                Holiday::firstOrCreate(['date' => $date], ['name' => $name, 'type' => 'regular']);
            }
        }
    }
}
