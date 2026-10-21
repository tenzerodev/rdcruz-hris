<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\LeaveLedger;
use App\Models\OvertimeAuthorization;
use App\Models\Position;
use App\Models\Punch;
use App\Models\TravelOrder;
use App\Models\User;
use App\Services\LeaveService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/** Sample people and attendance for trying the system: php artisan db:seed --class=DemoSeeder */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $mk = function (array $a, ?string $username = null) {
            $e = Employee::create($a + ['schedule_type' => 'flexi', 'is_active' => true, 'allow_personal' => true]);
            if ($username) {
                $u = User::create(['name' => $e->display_name, 'username' => $username, 'password' => 'password123', 'role' => 'employee']);
                $e->update(['user_id' => $u->id]);
            }

            return $e;
        };

        $p1 = Position::create(['item_no' => 'HRIS-DIR-1', 'title' => 'Division Chief', 'salary_grade' => 24, 'office' => 'Administrative Division']);
        $p2 = Position::create(['item_no' => 'HRIS-AO5-1', 'title' => 'Administrative Officer V', 'salary_grade' => 18, 'office' => 'Administrative Division']);
        Position::create(['item_no' => 'HRIS-AO2-1', 'title' => 'Administrative Officer II', 'salary_grade' => 11, 'office' => 'Administrative Division']);

        $chief = $mk(['employee_no' => '2015-001', 'employment_type' => 'permanent', 'last_name' => 'Villanueva', 'first_name' => 'Rosa', 'middle_name' => 'Cruz',
            'position' => 'Division Chief', 'position_id' => $p1->id, 'office' => 'Administrative Division', 'salary_grade' => 24, 'step' => 3, 'monthly_salary' => 98000,
            'date_hired' => '2015-03-02', 'sex' => 'female'], 'rvillanueva');
        $ao = $mk(['employee_no' => '2019-014', 'employment_type' => 'permanent', 'last_name' => 'Dela Cruz', 'first_name' => 'Juan', 'middle_name' => 'Santos',
            'position' => 'Administrative Officer V', 'position_id' => $p2->id, 'office' => 'Administrative Division', 'salary_grade' => 18, 'step' => 2, 'monthly_salary' => 52000,
            'supervisor_id' => $chief->id, 'date_hired' => '2019-06-03', 'sex' => 'male'], 'jdelacruz');
        $cos = $mk(['employee_no' => 'COS-2026-07', 'employment_type' => 'cos', 'last_name' => 'Garcia', 'first_name' => 'Mika',
            'position' => 'Graphic Artist', 'office' => 'Marketing', 'daily_rate' => 1500, 'supervisor_id' => $chief->id,
            'contract_start' => now()->startOfYear()->toDateString(), 'contract_end' => now()->addDays(20)->toDateString()], 'mgarcia');
        $mk(['employee_no' => 'SP-0042', 'employment_type' => 'service_provider', 'last_name' => 'Ramos', 'first_name' => 'Ben',
            'position' => 'Security Guard', 'office' => 'General Services', 'agency_name' => 'Sentinel Security Agency',
            'contract_start' => now()->startOfYear()->toDateString(), 'contract_end' => now()->endOfYear()->toDateString(), 'allow_personal' => false]);
        $mk(['employee_no' => 'DBP-118', 'employment_type' => 'dbp', 'last_name' => 'Torres', 'first_name' => 'Lito',
            'position' => 'Accountant III', 'office' => 'Finance', 'agency_name' => 'DBP Head Office', 'supervisor_id' => $chief->id], 'ltorres');

        foreach ([$chief, $ao] as $e) {
            LeaveLedger::create(['employee_id' => $e->id, 'balance' => 'VL', 'entry_date' => now()->startOfYear(), 'particulars' => 'Balance forwarded', 'earned' => 12.5, 'source' => 'adjustment']);
            LeaveLedger::create(['employee_id' => $e->id, 'balance' => 'SL', 'entry_date' => now()->startOfYear(), 'particulars' => 'Balance forwarded', 'earned' => 20, 'source' => 'adjustment']);
            LeaveLedger::create(['employee_id' => $e->id, 'balance' => 'SPL', 'entry_date' => now()->startOfYear(), 'particulars' => 'SPL '.now()->year, 'earned' => 3, 'source' => 'adjustment']);
        }

        // Last month's attendance: flexi arrivals 7:30–10:20, one absence, one Saturday of overtime.
        $start = CarbonImmutable::now()->subMonthNoOverflow()->startOfMonth();
        $people = Employee::all();
        for ($d = $start; $d->lte($start->endOfMonth()); $d = $d->addDay()) {
            foreach ($people as $i => $e) {
                if ($d->isWeekend() && ! ($d->isSaturday() && $d->day <= 7 && $e->id === $ao->id)) {
                    continue;
                }
                if ($e->id === $cos->id && $d->day === 10) {
                    continue; // absent
                }
                $inMin = 450 + (($d->day * 37 + $i * 53) % 170); // 7:30–10:20
                $in = $d->addMinutes($inMin);
                $out = $d->isSaturday() ? $in->addHours(5) : $in->addMinutes(540 + (($d->day * 13 + $i * 7) % 60) - 20);
                if ($e->id === $ao->id && $d->day % 5 === 0 && ! $d->isSaturday()) {
                    $out = $out->addHours(2)->addMinutes(30); // overtime days
                }
                Punch::create(['employee_id' => $e->id, 'work_date' => $d->toDateString(), 'type' => 'am_in', 'punched_at' => $in, 'source' => 'kiosk', 'liveness' => true]);
                Punch::create(['employee_id' => $e->id, 'work_date' => $d->toDateString(), 'type' => 'pm_out', 'punched_at' => $out, 'source' => 'kiosk', 'liveness' => true]);
            }
        }
        OvertimeAuthorization::create(['employee_id' => $ao->id, 'date_from' => $start->toDateString(), 'date_to' => $start->endOfMonth()->toDateString(),
            'max_minutes_per_day' => 240, 'purpose' => 'Trade fair preparations', 'reference_no' => 'OO-'.$start->format('Y').'-031', 'status' => 'approved']);

        $order = TravelOrder::create(['type' => 'ob', 'date_from' => now()->addDays(2)->toDateString(), 'date_to' => now()->addDays(2)->toDateString(),
            'destination' => 'DTI Main Office, Makati', 'purpose' => 'Coordination meeting', 'status' => 'pending', 'requested_by' => $ao->user_id]);
        $order->employees()->attach($ao->id);
        app(LeaveService::class)->file($ao, ['type' => 'VL', 'date_from' => now()->addWeekdays(5)->toDateString(), 'date_to' => now()->addWeekdays(6)->toDateString(),
            'details' => ['location' => 'ph'], 'reason' => 'Family trip'], $ao->user);
        app(LeaveService::class)->postMonth($start->year, $start->month);

        $this->command?->info('Demo data added. Logins (password: password123): rvillanueva (supervisor), jdelacruz, mgarcia (COS), ltorres (DBP).');
    }
}
