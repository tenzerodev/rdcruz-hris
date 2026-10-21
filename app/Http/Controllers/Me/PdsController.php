<?php

namespace App\Http\Controllers\Me;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Support\Pds;
use Illuminate\Http\Request;

/** Employees keep their own PDS up to date (it is their sworn declaration). */
class PdsController extends Controller
{
    public function edit(Request $request)
    {
        $employee = $request->user()->employee;

        return view('pds.edit', ['employee' => $employee, 'p' => $employee->pds ?? [], 'action' => route('me.profile.pds'), 'back' => route('me.profile')]);
    }

    public function update(Request $request)
    {
        $request->validate(['pds' => ['required', 'array']]);
        $employee = $request->user()->employee;
        Pds::save($employee, $request->input('pds'));
        AuditLog::record('pds.updated', $employee, ['by' => 'employee']);

        return redirect()->route('me.profile')->with('status', 'PDS saved.');
    }
}
