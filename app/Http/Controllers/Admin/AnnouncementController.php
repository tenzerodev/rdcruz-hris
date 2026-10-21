<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Support\Notify;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/** HR posts memos and office orders; employees acknowledge them; HR sees who hasn't. */
class AnnouncementController extends Controller
{
    public function index()
    {
        $items = Announcement::withCount(['reads as acknowledged_count' => fn ($q) => $q->whereNotNull('acknowledged_at')])
            ->orderByRaw('published_at is null desc')->orderByDesc('published_at')->orderByDesc('id')->paginate(30);

        return view('admin.announcements.index', ['items' => $items]);
    }

    public function create()
    {
        return view('admin.announcements.form', ['item' => new Announcement(['kind' => 'memo', 'requires_ack' => true]), 'offices' => $this->offices()]);
    }

    public function store(Request $request)
    {
        $item = Announcement::create($this->validated($request) + ['created_by' => $request->user()->id]);
        $this->saveAttachment($request, $item);
        AuditLog::record('announcement.created', $item, ['title' => $item->title]);
        if ($request->boolean('publish')) {
            return $this->publish($request, $item);
        }

        return redirect()->route('admin.announcements.show', $item)->with('status', 'Saved as draft. Employees see it once you publish.');
    }

    public function show(Request $request, Announcement $announcement)
    {
        $recipients = $announcement->recipients();
        $reads = $announcement->reads()->get()->keyBy('employee_id');

        if ($request->query('format') === 'csv') {
            return response()->streamDownload(function () use ($recipients, $reads) {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['Employee No', 'Name', 'Office', 'Read', 'Acknowledged']);
                foreach ($recipients as $e) {
                    $r = $reads[$e->id] ?? null;
                    fputcsv($out, [$e->employee_no, $e->full_name, $e->office, $r?->read_at?->format('Y-m-d H:i') ?? '', $r?->acknowledged_at?->format('Y-m-d H:i') ?? 'NOT YET']);
                }
                fclose($out);
            }, 'acknowledgments-'.$announcement->id.'.csv', ['Content-Type' => 'text/csv']);
        }

        return view('admin.announcements.show', ['item' => $announcement, 'recipients' => $recipients, 'reads' => $reads]);
    }

    public function edit(Announcement $announcement)
    {
        return view('admin.announcements.form', ['item' => $announcement, 'offices' => $this->offices()]);
    }

    public function update(Request $request, Announcement $announcement)
    {
        $announcement->update($this->validated($request));
        $this->saveAttachment($request, $announcement);
        AuditLog::record('announcement.updated', $announcement, ['title' => $announcement->title]);
        if ($request->boolean('publish') && ! $announcement->published_at) {
            return $this->publish($request, $announcement);
        }

        return redirect()->route('admin.announcements.show', $announcement)->with('status', 'Saved.');
    }

    /** Publishes now and notifies everyone it is for. */
    public function publish(Request $request, Announcement $announcement)
    {
        if (! $announcement->published_at) {
            $announcement->update(['published_at' => now()]);
            AuditLog::record('announcement.published', $announcement, ['title' => $announcement->title]);
            $recipients = $announcement->recipients()->load('user');
            $email = $request->boolean('email');
            foreach ($recipients as $e) {
                $lines = [$announcement->kind_name.($announcement->reference_no ? ' '.$announcement->reference_no : '').': '.$announcement->title,
                    $announcement->requires_ack ? 'Please read it and click “I have read this”.' : 'Open the HRIS to read it.'];
                if ($e->user) {
                    Notify::user($e->user, 'New '.mb_strtolower($announcement->kind_name).': '.$announcement->title, $lines, route('me.memos.show', $announcement), $email);
                } elseif ($email) {
                    Notify::employee($e, 'New '.mb_strtolower($announcement->kind_name).': '.$announcement->title, $lines);
                }
            }

            return redirect()->route('admin.announcements.show', $announcement)->with('status', 'Published to '.$recipients->count().' employee(s).');
        }

        return back();
    }

    /** Takes it down (back to draft); acknowledgments are kept. */
    public function unpublish(Announcement $announcement)
    {
        $announcement->update(['published_at' => null]);
        AuditLog::record('announcement.unpublished', $announcement);

        return back()->with('status', 'Taken down. Employees no longer see it.');
    }

    public function destroy(Announcement $announcement)
    {
        if ($announcement->attachment_path) {
            Storage::disk('local')->delete($announcement->attachment_path);
        }
        AuditLog::record('announcement.deleted', null, ['title' => $announcement->title]);
        $announcement->delete();

        return redirect()->route('admin.announcements.index')->with('status', 'Deleted.');
    }

    private function validated(Request $request): array
    {
        $groups = array_keys(config('hris.groups'));
        $data = $request->validate([
            'kind' => ['required', Rule::in(array_keys(Announcement::KINDS))],
            'reference_no' => ['nullable', 'string', 'max:60'],
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:20000'],
            'offices' => ['array'], 'offices.*' => ['string', 'max:150'],
            'groups' => ['array'], 'groups.*' => [Rule::in($groups)],
            'expires_on' => ['nullable', 'date', 'after_or_equal:today'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);
        $audience = array_filter(['offices' => array_values($data['offices'] ?? []), 'groups' => array_values($data['groups'] ?? [])]);

        return [
            'kind' => $data['kind'], 'reference_no' => $data['reference_no'] ?? null, 'title' => $data['title'], 'body' => $data['body'],
            'audience' => $audience ?: null, 'expires_on' => $data['expires_on'] ?? null,
            'requires_ack' => $request->boolean('requires_ack'), 'pinned' => $request->boolean('pinned'),
        ];
    }

    private function saveAttachment(Request $request, Announcement $item): void
    {
        if ($request->boolean('remove_attachment') && $item->attachment_path) {
            Storage::disk('local')->delete($item->attachment_path);
            $item->update(['attachment_path' => null, 'attachment_name' => null]);
        }
        if ($file = $request->file('attachment')) {
            if ($item->attachment_path) {
                Storage::disk('local')->delete($item->attachment_path);
            }
            $item->update(['attachment_path' => $file->store('announcements', 'local'), 'attachment_name' => mb_substr($file->getClientOriginalName(), 0, 200)]);
        }
    }

    /** @return list<string> */
    private function offices(): array
    {
        return Employee::where('is_active', true)->whereNotNull('office')->distinct()->orderBy('office')->pluck('office')->filter()->values()->all();
    }
}
