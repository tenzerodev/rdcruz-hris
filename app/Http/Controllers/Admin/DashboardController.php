<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\Kiosk;
use App\Models\LeaveApplication;
use App\Models\OvertimeAuthorization;
use App\Models\Position;
use App\Models\Punch;
use App\Models\TravelOrder;
use App\Services\Analytics;
use App\Services\DtrBuilder;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $today = today()->toDateString();
        $active = Employee::where('is_active', true);
        $presentIds = Punch::whereDate('work_date', $today)->distinct()->pluck('employee_id');

        $onLeave = LeaveApplication::with('employee')->where('status', 'approved')
            ->whereDate('date_from', '<=', $today)->whereDate('date_to', '>=', $today)->get();
        $onTravel = TravelOrder::with('employees')->where('status', 'approved')
            ->whereDate('date_from', '<=', $today)->whereDate('date_to', '>=', $today)->get();
        $awayIds = $onLeave->pluck('employee_id')->merge($onTravel->flatMap->employees->pluck('id'))->unique();

        $total = (clone $active)->count();
        $isWorkday = app(DtrBuilder::class)->isWorkday(today()->toImmutable());

        return view('admin.dashboard', [
            'isWorkday' => $isWorkday,
            'attendance' => [
                'total' => $total,
                'present' => $presentIds->count(),
                'away' => $awayIds->diff($presentIds)->count(),
                'not_in' => max(0, $total - $presentIds->count() - $awayIds->diff($presentIds)->count()),
            ],
            'enrolled' => (clone $active)->whereHas('faceSamples')->count(),
            'latest' => Punch::with('employee', 'kiosk:id,name')->whereDate('work_date', $today)->latest('punched_at')->limit(8)->get(),
            'notYet' => (clone $active)->whereNotIn('id', $presentIds->merge($awayIds))->orderBy('last_name')->get(),
            'kiosks' => Kiosk::orderBy('name')->get(),
            'inbox' => [
                'leave_ready' => LeaveApplication::where('status', 'recommended')->count(),
                'leave_waiting' => LeaveApplication::where('status', 'pending')->count(),
                'overtime' => OvertimeAuthorization::where('status', 'pending')->count(),
                'travel' => TravelOrder::where('status', 'pending')->count(),
            ],
            'onLeave' => $onLeave,
            'onTravel' => $onTravel,
            'expiring' => Employee::where('is_active', true)->whereNotNull('contract_end')
                ->whereDate('contract_end', '<=', today()->addDays(config('hris.contract_warning_days')))->orderBy('contract_end')->get(),
        ]);
    }

    public function workforce()
    {
        $employees = Employee::where('is_active', true)->with('plantillaPosition')->orderBy('last_name')->get();

        return view('admin.workforce', [
            'byGroup' => $employees->groupBy('group'),
            'byType' => $employees->countBy('employment_type'),
            'byOffice' => $employees->groupBy(fn ($e) => $e->office ?: '—')->map(fn ($g) => $g->countBy('group'))->sortKeys(),
            'agencies' => $employees->whereNotNull('agency_name')->groupBy('agency_name'),
            'contracts' => $employees->whereNotNull('contract_end')->sortBy('contract_end'),
            'positions' => Position::with('employee')->where('is_active', true)->get(),
        ]);
    }

    /** HR analytics: headcount, vacancies, turnover, leave, tardiness and demographics for a year. */
    public function analytics(Request $request, Analytics $analytics)
    {
        $year = $request->integer('year') ?: now()->year;

        return view('admin.analytics', ['year' => $year, 'a' => $analytics->build($year)]);
    }

    public function audit()
    {
        return view('admin.audit', [
            'logs' => AuditLog::with('user:id,name')->latest()->paginate(50),
        ]);
    }
}
