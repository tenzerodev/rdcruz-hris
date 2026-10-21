<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Services\FaceMatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class EnrollmentController extends Controller
{
    public function show(Employee $employee)
    {
        $employee->loadCount('faceSamples');

        return view('admin.employees.enroll', compact('employee'));
    }

    public function store(Request $request, Employee $employee, FaceMatcher $matcher)
    {
        $request->validate([
            'samples' => ['required', 'array', 'min:3', 'max:10'],
            'samples.*.descriptor' => ['required', 'array', 'size:'.FaceMatcher::LENGTH],
            'samples.*.score' => ['nullable', 'numeric'],
            'consent' => ['accepted'],
            'replace' => ['boolean'],
        ], ['consent.accepted' => 'The employee must give consent before their face data is stored.']);

        try {
            $samples = collect($request->input('samples'))->map(fn ($s) => [
                'descriptor' => FaceMatcher::validate($s['descriptor']),
                'score' => isset($s['score']) ? (float) $s['score'] : null,
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        // Block enrolling the same face under two employee records.
        foreach ($samples as $s) {
            $other = $matcher->match($s['descriptor'], null, $employee->id);
            if ($other['matched']) {
                return response()->json([
                    'ok' => false,
                    'message' => "This face is already enrolled as {$other['employee']->full_name} ({$other['employee']->employee_no}).",
                ], 422);
            }
        }

        // The samples should all be the same person.
        $list = $samples->pluck('descriptor')->all();
        for ($i = 1; $i < count($list); $i++) {
            if (FaceMatcher::distance($list[0], $list[$i]) > config('dtr.match_threshold') + 0.1) {
                return response()->json(['ok' => false, 'message' => 'The captures look like different people. Please retake with only one person in front of the camera.'], 422);
            }
        }

        DB::transaction(function () use ($request, $employee, $samples) {
            if ($request->boolean('replace')) {
                $employee->faceSamples()->delete();
            }
            foreach ($samples as $s) {
                $employee->faceSamples()->create($s);
            }
            $employee->forceFill(['consent_at' => $employee->consent_at ?? now(), 'face_purged_at' => null])->save();
        });
        AuditLog::record('face.enrolled', $employee, ['samples' => $samples->count()]);

        return response()->json([
            'ok' => true,
            'message' => 'Face enrolled for '.$employee->display_name.'.',
            'total' => $employee->faceSamples()->count(),
        ]);
    }

    public function destroy(Employee $employee)
    {
        $employee->faceSamples()->delete();
        $employee->update(['consent_at' => null]);
        AuditLog::record('face.deleted', $employee);

        return back()->with('status', 'Face data deleted for '.$employee->display_name.'.');
    }
}
