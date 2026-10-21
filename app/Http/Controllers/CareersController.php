<?php

namespace App\Http\Controllers;

use App\Models\Applicant;
use App\Models\AuditLog;
use App\Models\Vacancy;
use App\Support\Notify;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Public careers page: open vacancies, online application, and application status. */
class CareersController extends Controller
{
    public function index()
    {
        return view('careers.index', ['vacancies' => Vacancy::accepting()->orderBy('closing_on')->get()]);
    }

    public function show(Vacancy $vacancy)
    {
        abort_unless($vacancy->public && in_array($vacancy->status, ['open', 'closed', 'filled'], true), 404);

        return view('careers.show', ['vacancy' => $vacancy]);
    }

    public function apply(Request $request, Vacancy $vacancy)
    {
        abort_unless($vacancy->public && $vacancy->isAccepting(), 404);
        if ($request->filled('website')) { // honeypot: bots fill every field
            return redirect()->route('careers.index');
        }
        $docs = config('hris.recruitment.documents');
        $data = $request->validate([
            'last_name' => ['required', 'string', 'max:80'], 'first_name' => ['required', 'string', 'max:80'], 'middle_name' => ['nullable', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:150'], 'mobile' => ['required', 'string', 'max:30'], 'address' => ['nullable', 'string', 'max:250'],
            'birth_date' => ['nullable', 'date', 'before:-15 years'], 'sex' => ['nullable', 'in:male,female'],
            'current_position' => ['nullable', 'string', 'max:150'],
            'education' => ['required', 'string', 'max:2000'], 'training' => ['nullable', 'string', 'max:2000'],
            'experience' => ['nullable', 'string', 'max:3000'], 'eligibility' => ['nullable', 'string', 'max:1000'],
            'files' => ['array'], 'files.*' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'consent' => ['accepted'],
        ], ['consent.accepted' => 'Please agree to the privacy notice so we can process your application.', 'files.*.max' => 'Each file can be up to 5 MB.']);
        if (! $request->file('files.pds') || ! $request->file('files.letter')) {
            throw ValidationException::withMessages(['files' => 'Attach at least your letter of intent and Personal Data Sheet.']);
        }
        if (Applicant::where('vacancy_id', $vacancy->id)->where('email', $data['email'])->whereNotIn('status', ['withdrawn'])->exists()) {
            throw ValidationException::withMessages(['email' => 'An application with this email was already received for this position.']);
        }

        $applicant = DB::transaction(function () use ($data, $vacancy, $request, $docs) {
            $applicant = Applicant::create(collect($data)->except('files', 'consent')->all() + ['vacancy_id' => $vacancy->id, 'status' => 'received', 'consent_at' => now()]);
            $files = [];
            foreach ($docs as $key => $label) {
                if ($file = $request->file("files.$key")) {
                    $files[] = ['key' => $key, 'label' => $label, 'name' => Str::limit($file->getClientOriginalName(), 150, ''),
                        'path' => $file->store("applicants/{$vacancy->id}/{$applicant->reference_no}", 'local')];
                }
            }
            $applicant->update(['documents' => $files]);

            return $applicant;
        });
        AuditLog::record('recruitment.applied', $applicant, ['vacancy' => $vacancy->title]);
        Notify::address($applicant->email, "Application received: {$vacancy->title}", [
            "Thank you for applying for {$vacancy->title}. Your reference number is {$applicant->reference_no}.",
            'Keep this number to check the status of your application.',
        ], route('careers.status'));
        Notify::hr("New applicant for {$vacancy->title}", ["{$applicant->full_name} ({$applicant->reference_no})."], route('admin.recruitment.show', $vacancy), 'recruitment');

        return redirect()->route('careers.thanks')->with('reference', $applicant->reference_no);
    }

    public function thanks(Request $request)
    {
        return session('reference') ? view('careers.thanks', ['reference' => session('reference')]) : redirect()->route('careers.index');
    }

    public function status(Request $request)
    {
        $applicant = null;
        if ($request->filled('reference_no')) {
            $data = $request->validate(['reference_no' => ['required', 'string', 'max:20'], 'email' => ['required', 'email']]);
            $applicant = Applicant::with('vacancy')->where('reference_no', strtoupper(trim($data['reference_no'])))->first();
            if (! $applicant || ! hash_equals(mb_strtolower($applicant->email), mb_strtolower($data['email']))) {
                throw ValidationException::withMessages(['reference_no' => 'No application matches that reference number and email.']);
            }
        }

        return view('careers.status', ['applicant' => $applicant]);
    }
}
