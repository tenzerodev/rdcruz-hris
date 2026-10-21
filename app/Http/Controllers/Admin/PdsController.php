<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Support\Pds;
use Illuminate\Http\Request;

class PdsController extends Controller
{
    public function edit(Employee $employee)
    {
        return view('pds.edit', ['employee' => $employee, 'p' => $employee->pds ?? [], 'action' => route('admin.employees.pds', $employee), 'back' => route('admin.employees.show', [$employee, 'tab' => '201'])]);
    }

    public function update(Request $request, Employee $employee)
    {
        $request->validate(['pds' => ['required', 'array']]);
        Pds::save($employee, $request->input('pds'));
        AuditLog::record('pds.updated', $employee, ['by' => 'hr']);

        return redirect()->route('admin.employees.show', [$employee, 'tab' => '201'])->with('status', 'PDS saved.');
    }
}
