<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Services\BackupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class BackupController extends Controller
{
    public function __construct(private BackupService $backups) {}

    public function index()
    {
        return view('admin.backups', ['backups' => $this->backups->list(), 's' => Setting::allValues()]);
    }

    public function store(Request $request)
    {
        @set_time_limit(600);
        $name = $this->backups->create('manual', $request->boolean('snapshots') ?: null);
        $this->backups->prune((int) Setting::get('backup_keep', '14'));
        AuditLog::record('backup.created', null, ['file' => $name]);

        return back()->with('status', "Backup made: {$name}. Download a copy and keep it somewhere other than this server.");
    }

    public function download(string $name)
    {
        $path = $this->backups->path($name);
        AuditLog::record('backup.downloaded', null, ['file' => $name]);

        return response()->download($path, $name, ['Content-Type' => 'application/zip']);
    }

    public function destroy(string $name)
    {
        $this->backups->delete($name);
        AuditLog::record('backup.deleted', null, ['file' => $name]);

        return back()->with('status', "Deleted {$name}.");
    }

    /** Replaces all data with a backup. Needs the admin's password and typing RESTORE. */
    public function restore(Request $request)
    {
        $request->validate([
            'name' => ['nullable', 'required_without:file', 'string'],
            'file' => ['nullable', 'required_without:name', 'file', 'max:2097152'],
            'confirm' => ['required', 'in:RESTORE'],
            'password' => ['required', 'current_password'],
        ], ['confirm.in' => 'Type RESTORE in capital letters to confirm.']);
        @set_time_limit(1800);

        if ($request->hasFile('file')) {
            $stored = $request->file('file')->storeAs(BackupService::DIR, 'uploaded-'.now()->format('Ymd-His').'.zip', 'local');
            $path = storage_path('app/private/'.$stored);
            $label = $request->file('file')->getClientOriginalName();
        } else {
            $path = $this->backups->path($request->input('name'));
            $label = $request->input('name');
        }

        try {
            $manifest = $this->backups->inspect($path);
            $safety = $this->backups->restore($path);
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['file' => $e->getMessage()]);
        }

        // Accounts were replaced too, so everyone signs in again.
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        AuditLog::create(['action' => 'backup.restored', 'details' => ['file' => $label, 'made' => $manifest['created_at'], 'safety_backup' => $safety], 'ip' => $request->ip()]);

        return redirect()->route('login')->with('status', 'Restored the backup from '.$manifest['created_at'].'. Please sign in again. A safety backup of the data before the restore was saved as '.$safety.'.');
    }
}
