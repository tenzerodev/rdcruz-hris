<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Setting;
use App\Services\DtrBuilder;
use Illuminate\Http\Request;

class DtrController extends Controller
{
    public function index(Request $request)
    {
        return view('admin.dtr.index', [
            'employees' => Employee::where('is_active', true)->orderBy('last_name')->orderBy('first_name')->get(),
            'offices' => Employee::whereNotNull('office')->distinct()->orderBy('office')->pluck('office'),
            'month' => now()->format('Y-m'),
        ]);
    }

    public function print(Request $request, DtrBuilder $builder)
    {
        $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'employee_ids' => ['nullable', 'array'],
            'employee_ids.*' => ['integer'],
            'office' => ['nullable', 'string'],
        ]);
        $month = $request->date('month', 'Y-m');

        $employees = Employee::query()
            ->when($request->input('employee_ids'), fn ($q, $ids) => $q->whereIn('id', $ids),
                fn ($q) => $q->where('is_active', true))
            ->when($request->input('office'), fn ($q, $office) => $q->where('office', $office))
            ->orderBy('last_name')->orderBy('first_name')
            ->get();

        return view('dtr.print', [
            'records' => $employees->map(fn ($e) => $builder->build($e, $month->year, $month->month)),
            'settings' => Setting::allValues(),
            'backUrl' => route('admin.dtr.index'),
        ]);
    }
}
