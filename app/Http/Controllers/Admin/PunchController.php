<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\Punch;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class PunchController extends Controller
{
    public function index(Request $request)
    {
        $from = $request->date('from') ?? today();
        $to = $request->date('to') ?? $from;

        $punches = $this->filtered($request, $from, $to)
            ->with(['employee', 'kiosk:id,name', 'creator:id,name', 'device:id,name'])
            ->orderByDesc('punched_at')
            ->paginate(50)->withQueryString();

        return view('admin.punches.index', [
            'punches' => $punches,
            'from' => $from,
            'to' => $to,
            'employees' => Employee::orderBy('last_name')->get(['id', 'last_name', 'first_name', 'middle_name', 'suffix']),
        ]);
    }

    public function export(Request $request)
    {
        $from = $request->date('from') ?? today();
        $to = $request->date('to') ?? $from;
        $rows = $this->filtered($request, $from, $to)->with(['employee', 'kiosk:id,name'])
            ->orderBy('punched_at')->get();

        $filename = 'punches-'.$from->format('Ymd').'-'.$to->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Employee No', 'Name', 'Office', 'Date', 'Type', 'Time', 'Source', 'Kiosk', 'Match distance', 'Liveness', 'Latitude', 'Longitude', 'Remarks']);
            foreach ($rows as $p) {
                fputcsv($out, [
                    $p->employee->employee_no, $p->employee->full_name, $p->employee->office,
                    $p->work_date->toDateString(), $p->label, $p->punched_at->format('H:i:s'),
                    $p->source, $p->kiosk?->name, $p->distance, $p->liveness ? 'yes' : 'no',
                    $p->latitude, $p->longitude, $p->remarks,
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function create(Request $request)
    {
        return view('admin.punches.form', [
            'punch' => new Punch([
                'employee_id' => $request->integer('employee_id') ?: null,
                'work_date' => $request->date('date') ?? today(),
                'type' => $request->query('type', 'am_in'),
            ]),
            'employees' => Employee::where('is_active', true)->orderBy('last_name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'work_date' => ['required', 'date'],
            'type' => ['required', Rule::in(Punch::TYPES)],
            'time' => ['required', 'date_format:H:i'],
            'remarks' => ['required', 'string', 'max:250'],
        ]);
        $request->validate(['type' => [Rule::unique('punches')->where(fn ($q) => $q
            ->where('employee_id', $data['employee_id'])->whereDate('work_date', $data['work_date']))]],
            ['type.unique' => 'That punch already exists for this day. Edit it instead.']);

        $punch = Punch::create([
            'employee_id' => $data['employee_id'],
            'work_date' => $data['work_date'],
            'type' => $data['type'],
            'punched_at' => Carbon::parse($data['work_date'].' '.$data['time']),
            'source' => 'manual',
            'created_by' => $request->user()->id,
            'remarks' => $data['remarks'],
            'ip' => $request->ip(),
        ]);
        AuditLog::record('punch.created', $punch, $data);

        return redirect()->route('admin.punches.index', ['from' => $data['work_date']])->with('status', 'Punch added.');
    }

    public function edit(Punch $punch)
    {
        return view('admin.punches.form', ['punch' => $punch->load('employee'), 'employees' => collect()]);
    }

    public function update(Request $request, Punch $punch)
    {
        $data = $request->validate([
            'time' => ['required', 'date_format:H:i'],
            'remarks' => ['required', 'string', 'max:250'],
        ]);

        $before = $punch->punched_at->format('H:i');
        $punch->update([
            'punched_at' => Carbon::parse($punch->work_date->toDateString().' '.$data['time']),
            'remarks' => $data['remarks'],
        ]);
        AuditLog::record('punch.updated', $punch, ['from' => $before, 'to' => $data['time'], 'remarks' => $data['remarks']]);

        return redirect()->route('admin.punches.index', ['from' => $punch->work_date->toDateString()])->with('status', 'Punch updated.');
    }

    public function destroy(Request $request, Punch $punch)
    {
        AuditLog::record('punch.deleted', $punch, [
            'employee_id' => $punch->employee_id, 'type' => $punch->type,
            'punched_at' => $punch->punched_at->toDateTimeString(), 'source' => $punch->source,
        ]);
        if ($punch->snapshot_path) {
            Storage::disk('local')->delete($punch->snapshot_path);
        }
        $punch->delete();

        return back()->with('status', 'Punch deleted.');
    }

    public function snapshot(Punch $punch)
    {
        abort_unless($punch->snapshot_path && Storage::disk('local')->exists($punch->snapshot_path), 404);

        return Storage::disk('local')->response($punch->snapshot_path, null, [
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    private function filtered(Request $request, $from, $to)
    {
        return Punch::query()
            ->whereDate('work_date', '>=', $from->toDateString())->whereDate('work_date', '<=', $to->toDateString())
            ->when($request->integer('employee_id'), fn ($q, $id) => $q->where('employee_id', $id))
            ->when($request->query('source'), fn ($q, $s) => $q->where('source', $s));
    }
}
