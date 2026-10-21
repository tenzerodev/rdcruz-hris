<?php

namespace App\Http\Controllers\Me;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\AnnouncementRead;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** Memos and announcements for the employee, with "I have read this". */
class AnnouncementController extends Controller
{
    public function index(Request $request)
    {
        $employee = $request->user()->employee;

        return view('me.memos', [
            'items' => Announcement::forEmployee($employee),
            'reads' => AnnouncementRead::where('employee_id', $employee->id)->get()->keyBy('announcement_id'),
        ]);
    }

    public function show(Request $request, Announcement $announcement)
    {
        $employee = $request->user()->employee;
        abort_unless($announcement->isPublished() && $announcement->isFor($employee), 404);
        $read = AnnouncementRead::firstOrCreate(['announcement_id' => $announcement->id, 'employee_id' => $employee->id], ['read_at' => now()]);

        return view('me.memo', ['item' => $announcement, 'read' => $read]);
    }

    public function acknowledge(Request $request, Announcement $announcement)
    {
        $employee = $request->user()->employee;
        abort_unless($announcement->isPublished() && $announcement->isFor($employee), 404);
        $read = AnnouncementRead::firstOrNew(['announcement_id' => $announcement->id, 'employee_id' => $employee->id]);
        $read->read_at ??= now();
        $read->acknowledged_at ??= now();
        $read->save();
        AuditLog::record('announcement.acknowledged', $announcement, ['employee' => $employee->id]);

        return back()->with('status', 'Thank you. Your acknowledgment was recorded on '.$read->acknowledged_at->format('M j, Y g:i A').'.');
    }

    /** The attached file: for the employees it is addressed to, and staff who manage announcements. */
    public function attachment(Request $request, Announcement $announcement)
    {
        $user = $request->user();
        $staff = $user->canSee('announcements');
        abort_unless($announcement->attachment_path && ($staff || ($user->employee && $announcement->isPublished() && $announcement->isFor($user->employee))), 404);

        return Storage::disk('local')->response($announcement->attachment_path, $announcement->attachment_name);
    }
}
