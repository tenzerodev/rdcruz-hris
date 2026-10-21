<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\Education;
use App\Models\Eligibility;
use App\Models\Employee;
use App\Models\ServiceRecord;
use App\Models\Training;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/** 201 file entries: education, eligibility, training, service record, documents. */
class RecordController extends Controller
{
    private const KINDS = [
        'education' => Education::class, 'eligibility' => Eligibility::class, 'training' => Training::class, 'service' => ServiceRecord::class,
    ];

    public function store(Request $request, Employee $employee, string $kind)
    {
        abort_unless(isset(self::KINDS[$kind]), 404);
        $record = self::KINDS[$kind]::create($this->rules($request, $kind) + ['employee_id' => $employee->id]);
        AuditLog::record("201.$kind.added", $employee, ['id' => $record->id]);

        return redirect()->route('admin.employees.show', [$employee, 'tab' => $kind === 'service' ? 'service' : '201'])->with('status', 'Saved.');
    }

    public function destroy(Employee $employee, string $kind, int $id)
    {
        abort_unless(isset(self::KINDS[$kind]), 404);
        $record = self::KINDS[$kind]::where('employee_id', $employee->id)->findOrFail($id);
        AuditLog::record("201.$kind.deleted", $employee, $record->toArray());
        $record->delete();

        return back()->with('status', 'Removed.');
    }

    public function upload(Request $request, Employee $employee)
    {
        $data = $request->validate([
            'category' => ['required', Rule::in(array_keys(Document::CATEGORIES))],
            'title' => ['required', 'string', 'max:150'],
            'file' => ['required', 'file', 'max:20480', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx'],
        ]);
        $file = $request->file('file');
        $doc = $employee->documents()->create([
            'category' => $data['category'], 'title' => $data['title'],
            'path' => $file->store("201/{$employee->id}", 'local'),
            'original_name' => $file->getClientOriginalName(), 'mime' => $file->getMimeType(), 'size' => $file->getSize(),
            'uploaded_by' => $request->user()->id,
        ]);
        AuditLog::record('201.document.uploaded', $employee, ['document' => $doc->id, 'title' => $doc->title]);

        return redirect()->route('admin.employees.show', [$employee, 'tab' => 'documents'])->with('status', 'Document uploaded.');
    }

    public function download(Request $request, Document $document)
    {
        abort_unless($request->user()->canView($document->employee), 403);
        abort_unless(Storage::disk('local')->exists($document->path), 404);

        return Storage::disk('local')->download($document->path, $document->original_name);
    }

    public function destroyDocument(Document $document)
    {
        AuditLog::record('201.document.deleted', $document->employee, ['title' => $document->title]);
        Storage::disk('local')->delete($document->path);
        $document->delete();

        return back()->with('status', 'Document deleted.');
    }

    private function rules(Request $request, string $kind): array
    {
        return match ($kind) {
            'education' => $request->validate([
                'level' => ['required', Rule::in(array_keys(Education::LEVELS))], 'school' => ['required', 'string', 'max:200'],
                'degree' => ['nullable', 'string', 'max:200'], 'year_from' => ['nullable', 'digits:4'], 'year_to' => ['nullable', 'digits:4'],
                'year_graduated' => ['nullable', 'digits:4'], 'honors' => ['nullable', 'string', 'max:200'],
            ]),
            'eligibility' => $request->validate([
                'name' => ['required', 'string', 'max:200'], 'rating' => ['nullable', 'string', 'max:20'], 'exam_date' => ['nullable', 'date'],
                'exam_place' => ['nullable', 'string', 'max:200'], 'license_no' => ['nullable', 'string', 'max:60'], 'valid_until' => ['nullable', 'date'],
            ]),
            'training' => $request->validate([
                'title' => ['required', 'string', 'max:250'], 'date_from' => ['required', 'date'], 'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
                'hours' => ['nullable', 'integer', 'min:1', 'max:2000'], 'type' => ['nullable', Rule::in(array_keys(Training::TYPES))], 'sponsor' => ['nullable', 'string', 'max:200'],
            ]),
            'service' => $request->validate([
                'date_from' => ['required', 'date'], 'date_to' => ['nullable', 'date', 'after_or_equal:date_from'], 'designation' => ['required', 'string', 'max:150'],
                'status' => ['required', 'string', 'max:50'], 'annual_salary' => ['nullable', 'numeric', 'min:0'], 'office' => ['nullable', 'string', 'max:150'],
                'branch' => ['nullable', 'string', 'max:150'], 'leave_without_pay' => ['nullable', 'string', 'max:100'],
                'separation' => ['nullable', 'string', 'max:150'], 'remarks' => ['nullable', 'string', 'max:200'],
            ]),
        };
    }
}
