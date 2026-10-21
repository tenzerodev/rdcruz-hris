<?php

namespace App\Http\Controllers\Me;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Support\Notify;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** The employee's data privacy rights: see what is held, download a copy, withdraw face consent. */
class PrivacyController extends Controller
{
    public function index(Request $request)
    {
        $e = $request->user()->employee;

        return view('me.privacy', [
            'e' => $e,
            'counts' => [
                'face' => $e->faceSamples()->count(),
                'punches' => $e->punches()->count(),
                'photos' => $e->punches()->whereNotNull('snapshot_path')->count(),
                'documents' => $e->documents()->count(),
                'leave' => $e->leaveApplications()->count(),
            ],
        ]);
    }

    /** Everything about the employee, as a JSON file (right to data portability). */
    public function export(Request $request)
    {
        $e = $request->user()->employee->load(['serviceRecords', 'educations', 'eligibilities', 'trainings', 'documents', 'leaveApplications', 'plantillaPosition']);
        AuditLog::record('privacy.data_exported', $e);

        $data = [
            'exported_at' => now()->toIso8601String(),
            'note' => 'Your personal data held by the HRIS. Face recognition is stored only as numbers (a face template), not as a photo. Ask the Data Protection Officer if anything is wrong.',
            'profile' => collect($e->toArray())->except(['pds', 'user_id', 'service_records', 'educations', 'eligibilities', 'trainings', 'documents', 'leave_applications', 'plantilla_position'])->all(),
            'personal_data_sheet' => $e->pds,
            'service_records' => $e->serviceRecords->toArray(),
            'education' => $e->educations->toArray(),
            'eligibilities' => $e->eligibilities->toArray(),
            'trainings' => $e->trainings->toArray(),
            'documents' => $e->documents->map->only(['id', 'category', 'title', 'original_name', 'created_at'])->all(),
            'leave_applications' => $e->leaveApplications->toArray(),
            'leave_card' => $e->ledger()->orderBy('id')->get()->toArray(),
            'time_records' => $e->punches()->orderBy('punched_at')->get(['work_date', 'type', 'punched_at', 'source'])->toArray(),
            'overtime' => $e->overtimeAuthorizations()->get()->toArray(),
            'travel_orders' => $e->travelOrders()->get()->toArray(),
            'face_recognition' => ['consent_given' => $e->consent_at?->toIso8601String(), 'templates_stored' => $e->faceSamples()->count()],
            'sign_in' => $request->user()->only(['username', 'email', 'role', 'last_login_at', 'last_login_ip']),
        ];

        return response()->streamDownload(fn () => print (json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)),
            'my-hris-data-'.now()->format('Ymd').'.json', ['Content-Type' => 'application/json']);
    }

    /** Withdraw consent for face recognition: deletes the face templates and punch photos. */
    public function withdraw(Request $request)
    {
        $request->validate(['password' => ['required', 'current_password']]);
        $e = $request->user()->employee;

        $photos = 0;
        $e->punches()->whereNotNull('snapshot_path')->each(function ($p) use (&$photos) {
            Storage::disk('local')->delete($p->snapshot_path);
            $p->forceFill(['snapshot_path' => null])->saveQuietly();
            $photos++;
        });
        $templates = $e->faceSamples()->delete();
        $e->update(['consent_at' => null]);
        AuditLog::record('privacy.face_consent_withdrawn', $e, ['templates' => $templates, 'photos' => $photos]);
        Notify::hr('Face recognition consent withdrawn: '.$e->full_name, [
            "{$e->full_name} withdrew consent for face recognition. Their face data was deleted.",
            'Arrange another way for them to record attendance (logbook or manual entry by HR).',
        ], route('admin.employees.show', $e));

        return back()->with('status', 'Done. Your face data was deleted and face recognition is off for you. HR has been told so they can arrange another way to record your attendance.');
    }
}
