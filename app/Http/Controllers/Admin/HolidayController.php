<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Holiday;
use Illuminate\Http\Request;

class HolidayController extends Controller
{
    public function index(Request $request)
    {
        $year = $request->integer('year') ?: now()->year;

        return view('admin.holidays.index', [
            'year' => $year,
            'holidays' => Holiday::whereYear('date', $year)->orderBy('date')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $holiday = Holiday::create($request->validate([
            'date' => ['required', 'date', 'unique:holidays,date'],
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', 'in:'.implode(',', array_keys(Holiday::TYPES))],
        ]));
        AuditLog::record('holiday.created', $holiday, $holiday->only('date', 'name'));

        return back()->with('status', 'Holiday added.');
    }

    public function destroy(Holiday $holiday)
    {
        AuditLog::record('holiday.deleted', $holiday, ['date' => $holiday->date->toDateString(), 'name' => $holiday->name]);
        $holiday->delete();

        return back()->with('status', 'Holiday removed.');
    }
}
