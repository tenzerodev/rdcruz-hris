<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
use App\Models\ApplicantAssessment;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\Position;
use App\Models\ServiceRecord;
use App\Models\Setting;
use App\Models\Vacancy;
use App\Support\Notify;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Recruitment, selection and placement: vacancies, applicants, HRMPSB scores, appointment. */
class RecruitmentController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'active');

        return view('admin.recruitment.index', [
            'status' => $status,
            'vacancies' => Vacancy::withCount('applicants')
                ->when($status === 'active', fn ($q) => $q->whereIn('status', ['draft', 'open', 'closed']))
                ->when(! in_array($status, ['active', 'all'], true), fn ($q) => $q->where('status', $status))
                ->latest()->paginate(30)->withQueryString(),
            'vacant' => Position::where('is_active', true)->whereDoesntHave('employee', fn ($q) => $q->where('is_active', true))
                ->whereDoesntHave('vacancies', fn ($q) => $q->whereIn('status', ['draft', 'open', 'closed']))->orderBy('item_no')->get(),
        ]);
    }

    public function create(Request $request)
    {
        $vacancy = new Vacancy(['slots' => 1, 'public' => true, 'employment_type' => 'permanent', 'posted_on' => today(), 'closing_on' => today()->addDays(15)]);
        if ($position = Position::find($request->query('position'))) {
            $vacancy->fill(['position_id' => $position->id, 'title' => $position->title, 'item_no' => $position->item_no,
                'salary_grade' => $position->salary_grade, 'place_of_assignment' => $position->office]);
        }

        return view('admin.recruitment.form', ['vacancy' => $vacancy, 'positions' => Position::where('is_active', true)->orderBy('item_no')->get()]);
    }

    public function store(Request $request)
    {
        $vacancy = Vacancy::create($this->validated($request) + ['status' => 'draft', 'created_by' => $request->user()->id]);
        AuditLog::record('recruitment.vacancy_created', $vacancy, ['title' => $vacancy->title]);

        return redirect()->route('admin.recruitment.show', $vacancy)->with('status', 'Vacancy saved as draft. Open it when it is ready to publish.');
    }

    public function edit(Vacancy $vacancy)
    {
        return view('admin.recruitment.form', ['vacancy' => $vacancy, 'positions' => Position::where('is_active', true)->orderBy('item_no')->get()]);
    }

    public function update(Request $request, Vacancy $vacancy)
    {
        $vacancy->update($this->validated($request));
        AuditLog::record('recruitment.vacancy_updated', $vacancy);

        return redirect()->route('admin.recruitment.show', $vacancy)->with('status', 'Saved.');
    }

    public function show(Request $request, Vacancy $vacancy)
    {
        $applicants = $vacancy->applicants()->with('assessments', 'employee')->get()
            ->sortBy([fn ($a, $b) => ($b->averageScores()['total'] ?? -1) <=> ($a->averageScores()['total'] ?? -1), fn ($a, $b) => $a->last_name <=> $b->last_name])->values();

        return view('admin.recruitment.show', ['vacancy' => $vacancy, 'applicants' => $applicants]);
    }

    /** Open (publish), close for assessment, cancel or reopen. */
    public function setStatus(Request $request, Vacancy $vacancy)
    {
        $status = $request->validate(['status' => ['required', Rule::in(['open', 'closed', 'cancelled', 'draft'])]])['status'];
        $vacancy->update(['status' => $status] + ($status === 'open' && ! $vacancy->posted_on ? ['posted_on' => today()] : []));
        AuditLog::record('recruitment.vacancy_'.$status, $vacancy);

        return back()->with('status', match ($status) {
            'open' => $vacancy->public ? 'Published on the careers page.' : 'Open (not shown on the careers page).',
            'closed' => 'Closed. No more applications; continue with the assessment.',
            'cancelled' => 'Vacancy cancelled.',
            default => 'Back to draft.',
        });
    }

    /** HR records an application received on paper or by email. */
    public function storeApplicant(Request $request, Vacancy $vacancy)
    {
        $data = $request->validate([
            'employee_id' => ['nullable', 'exists:employees,id'],
            'last_name' => ['required', 'string', 'max:80'], 'first_name' => ['required', 'string', 'max:80'], 'middle_name' => ['nullable', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:150'], 'mobile' => ['nullable', 'string', 'max:30'], 'current_position' => ['nullable', 'string', 'max:150'],
            'education' => ['nullable', 'string', 'max:2000'], 'experience' => ['nullable', 'string', 'max:3000'],
            'training' => ['nullable', 'string', 'max:2000'], 'eligibility' => ['nullable', 'string', 'max:1000'],
        ]);
        $applicant = Applicant::create($data + ['vacancy_id' => $vacancy->id, 'status' => 'received', 'documents' => []]);
        AuditLog::record('recruitment.applicant_added', $applicant);

        return redirect()->route('admin.recruitment.applicant', $applicant)->with('status', "Added. Reference no. {$applicant->reference_no}.");
    }

    public function applicant(Applicant $applicant)
    {
        $applicant->load('vacancy', 'assessments', 'employee');

        return view('admin.recruitment.applicant', [
            'applicant' => $applicant,
            'scores' => $applicant->averageScores(),
            'types' => config('hris.employment_types'),
        ]);
    }

    public function updateApplicant(Request $request, Applicant $applicant)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_diff(array_keys(Applicant::STATUSES), ['appointed']))],
            'remarks' => ['nullable', 'string', 'max:500'],
            'notify' => ['boolean'],
        ]);
        abort_if($applicant->status === 'appointed', 422, 'Already appointed.');
        $applicant->update(['status' => $data['status'], 'remarks' => $data['remarks'] ?? $applicant->remarks]);
        AuditLog::record('recruitment.applicant_'.$data['status'], $applicant);
        if ($request->boolean('notify')) {
            Notify::address($applicant->email, "Your application for {$applicant->vacancy->title}", [
                'Status: '.Applicant::PUBLIC_STATUS[$applicant->status], ...($data['remarks'] ? [$data['remarks']] : []),
            ], route('careers.status'));
        }

        return back()->with('status', 'Status set to '.$applicant->status_label.($request->boolean('notify') ? ', and the applicant was emailed.' : '.'));
    }

    /** One HRMPSB member's comparative assessment scores. */
    public function assess(Request $request, Applicant $applicant)
    {
        $criteria = config('hris.recruitment.criteria');
        $rules = ['assessor' => ['required', 'string', 'max:120'], 'remarks' => ['nullable', 'string', 'max:500']];
        foreach ($criteria as $key => $c) {
            $rules["scores.$key"] = ['required', 'numeric', 'min:0', 'max:'.$c['max']];
        }
        $data = $request->validate($rules);
        $scores = array_map('floatval', array_intersect_key($data['scores'], $criteria));
        ApplicantAssessment::updateOrCreate(['applicant_id' => $applicant->id, 'assessor' => trim($data['assessor'])], [
            'scores' => $scores, 'total' => round(array_sum($scores), 2), 'remarks' => $data['remarks'] ?? null, 'entered_by' => $request->user()->id,
        ]);
        AuditLog::record('recruitment.assessed', $applicant, ['assessor' => $data['assessor'], 'total' => array_sum($scores)]);

        return back()->with('status', 'Scores saved for '.$data['assessor'].': '.round(array_sum($scores), 2).' points.');
    }

    public function destroyAssessment(ApplicantAssessment $assessment)
    {
        AuditLog::record('recruitment.assessment_deleted', $assessment->applicant, ['assessor' => $assessment->assessor]);
        $assessment->delete();

        return back()->with('status', 'Scores removed.');
    }

    public function document(Applicant $applicant, int $index)
    {
        $doc = $applicant->documents[$index] ?? abort(404);
        abort_unless(Storage::disk('local')->exists($doc['path']), 404);
        AuditLog::record('recruitment.document_viewed', $applicant, ['document' => $doc['label']]);

        return Storage::disk('local')->response($doc['path'], $doc['name']);
    }

    /** Comparative assessment result (ranking), for the HRMPSB and the appointing authority. */
    public function comparative(Vacancy $vacancy)
    {
        $applicants = $vacancy->applicants()->with('assessments')->whereIn('status', ['qualified', 'shortlisted', 'selected', 'not_selected', 'appointed'])->get()
            ->map(fn ($a) => ['applicant' => $a, 'scores' => $a->averageScores()])
            ->sortByDesc(fn ($r) => $r['scores']['total'] ?? -1)->values();
        AuditLog::record('recruitment.comparative_printed', $vacancy);

        return view('print.comparative-assessment', ['vacancy' => $vacancy, 'rows' => $applicants, 'settings' => Setting::allValues()]);
    }

    /** CS Form No. 9: request for publication of vacant positions. */
    public function publication(Request $request)
    {
        $ids = array_filter((array) $request->query('ids'));
        $vacancies = Vacancy::when($ids, fn ($q) => $q->whereIn('id', $ids), fn ($q) => $q->where('status', 'open'))->orderBy('item_no')->get();

        return view('print.cs-form-9', ['vacancies' => $vacancies, 'settings' => Setting::allValues()]);
    }

    /**
     * Appoints a selected applicant: a new employee record (or, for an internal applicant, the new
     * position on their record) with a service record line. Fills the vacancy when all slots are taken.
     */
    public function appoint(Request $request, Applicant $applicant)
    {
        $vacancy = $applicant->vacancy;
        abort_unless(in_array($applicant->status, ['selected', 'shortlisted', 'qualified'], true), 422, 'Select the applicant first.');
        $data = $request->validate([
            'employee_no' => [$applicant->employee_id ? 'nullable' : 'required', 'string', 'max:30', Rule::unique('employees', 'employee_no')],
            'employment_type' => ['required', Rule::in(array_keys(config('hris.employment_types')))],
            'date_hired' => ['required', 'date'],
            'step' => ['required', 'integer', 'min:1', 'max:8'],
            'monthly_salary' => ['required', 'numeric', 'min:0'],
        ]);

        $employee = DB::transaction(function () use ($applicant, $vacancy, $data) {
            $fields = [
                'employment_type' => $data['employment_type'], 'position' => $vacancy->title, 'position_id' => $vacancy->position_id,
                'salary_grade' => $vacancy->salary_grade, 'step' => $data['step'], 'monthly_salary' => $data['monthly_salary'],
                'office' => $vacancy->place_of_assignment, 'step_date' => $data['date_hired'], 'is_active' => true,
            ];
            if ($applicant->employee) {
                $employee = $applicant->employee;
                $employee->update($fields);
                $remarks = 'Promotion / transfer';
            } else {
                $employee = Employee::create($fields + [
                    'employee_no' => $data['employee_no'], 'last_name' => $applicant->last_name, 'first_name' => $applicant->first_name,
                    'middle_name' => $applicant->middle_name, 'email' => $applicant->email, 'mobile' => $applicant->mobile, 'address' => $applicant->address,
                    'birth_date' => $applicant->birth_date, 'sex' => $applicant->sex, 'date_hired' => $data['date_hired'], 'schedule_type' => 'fixed',
                ]);
                $remarks = 'Original appointment';
            }
            if ($vacancy->position_id) {
                Employee::where('position_id', $vacancy->position_id)->whereKeyNot($employee->id)->update(['position_id' => null]);
            }
            $open = ServiceRecord::where('employee_id', $employee->id)->whereNull('date_to')->orderByDesc('date_from')->first();
            if ($open && $open->date_from->lt($data['date_hired'])) {
                $open->update(['date_to' => now()->parse($data['date_hired'])->subDay()->toDateString()]);
            }
            ServiceRecord::create([
                'employee_id' => $employee->id, 'date_from' => $data['date_hired'], 'designation' => $vacancy->title,
                'status' => config("hris.employment_types.{$data['employment_type']}.label"), 'annual_salary' => round($data['monthly_salary'] * 12, 2),
                'office' => $vacancy->place_of_assignment, 'branch' => 'National', 'remarks' => $remarks,
            ]);
            $applicant->update(['status' => 'appointed', 'employee_id' => $employee->id]);
            if ($vacancy->applicants()->where('status', 'appointed')->count() >= $vacancy->slots) {
                $vacancy->update(['status' => 'filled']);
            }

            return $employee;
        });
        AuditLog::record('recruitment.appointed', $applicant, ['employee' => $employee->id, 'vacancy' => $vacancy->title]);

        return redirect()->route('admin.employees.edit', $employee)->with('status', "{$employee->full_name} appointed as {$vacancy->title}. Complete the 201 record, then enroll their face.");
    }

    /** Data privacy: deletes the uploaded files of a vacancy's applicants who were not appointed. */
    public function purgeFiles(Vacancy $vacancy)
    {
        abort_unless(in_array($vacancy->status, ['filled', 'cancelled'], true), 422, 'Only for filled or cancelled vacancies.');
        $count = self::purgeApplicantFiles($vacancy);
        AuditLog::record('recruitment.files_purged', $vacancy, ['applicants' => $count]);

        return back()->with('status', "Deleted the files of {$count} applicant(s).");
    }

    public static function purgeApplicantFiles(Vacancy $vacancy): int
    {
        $count = 0;
        foreach ($vacancy->applicants()->where('status', '!=', 'appointed')->whereNull('files_purged_at')->get() as $a) {
            foreach ($a->documents ?? [] as $doc) {
                Storage::disk('local')->delete($doc['path']);
            }
            $a->forceFill(['documents' => [], 'files_purged_at' => now()])->save();
            $count++;
        }

        return $count;
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'position_id' => ['nullable', 'exists:positions,id'],
            'title' => ['required', 'string', 'max:150'], 'item_no' => ['nullable', 'string', 'max:60'],
            'salary_grade' => ['nullable', 'integer', 'min:1', 'max:33'], 'monthly_salary' => ['nullable', 'numeric', 'min:0'],
            'employment_type' => ['required', Rule::in(array_keys(config('hris.employment_types')))],
            'place_of_assignment' => ['nullable', 'string', 'max:150'],
            'education' => ['nullable', 'string', 'max:2000'], 'training' => ['nullable', 'string', 'max:2000'],
            'experience' => ['nullable', 'string', 'max:2000'], 'eligibility' => ['nullable', 'string', 'max:2000'],
            'competencies' => ['nullable', 'string', 'max:3000'], 'instructions' => ['nullable', 'string', 'max:3000'],
            'slots' => ['required', 'integer', 'min:1', 'max:200'],
            'posted_on' => ['nullable', 'date'], 'closing_on' => ['nullable', 'date', 'after_or_equal:posted_on'],
        ]);
        if ($data['position_id'] ?? null) {
            $taken = Vacancy::where('position_id', $data['position_id'])->whereIn('status', ['draft', 'open', 'closed'])
                ->when($request->route('vacancy'), fn ($q, $v) => $q->whereKeyNot($v->id))->exists();
            if ($taken) {
                throw ValidationException::withMessages(['position_id' => 'This plantilla item already has an active vacancy.']);
            }
        }

        return $data + ['public' => $request->boolean('public')];
    }
}
