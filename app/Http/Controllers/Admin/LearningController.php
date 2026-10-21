<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\Setting;
use App\Models\Training;
use App\Models\TrainingNeed;
use App\Models\TrainingParticipant;
use App\Models\TrainingProgram;
use App\Support\Notify;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Learning & development: needs survey results, the yearly L&D plan, nominations and training hours. */
class LearningController extends Controller
{
    public function index(Request $request)
    {
        $year = $request->integer('year') ?: now()->year;
        $tab = $request->query('tab', 'plan');
        $programs = TrainingProgram::withCount(['participants', 'participants as attended_count' => fn ($q) => $q->where('status', 'attended')])
            ->where('year', $year)->orderByRaw('coalesce(date_from, ?)', ["$year-12-31"])->orderBy('quarter')->get();

        return view('admin.learning.index', [
            'year' => $year,
            'tab' => $tab,
            'programs' => $programs,
            'needs' => $tab === 'needs' ? TrainingNeed::with('employee', 'program')->where('year', $year)->get() : collect(),
            'hours' => $tab === 'hours' ? $this->hours($year) : collect(),
            'target' => (int) config('hris.learning.target_hours', 40),
        ]);
    }

    public function store(Request $request)
    {
        $program = TrainingProgram::create($this->validated($request) + ['status' => 'planned']);
        if ($ids = $request->input('need_ids')) {
            TrainingNeed::whereIn('id', (array) $ids)->update(['training_program_id' => $program->id]);
        }
        AuditLog::record('learning.program_created', $program, ['title' => $program->title]);

        return redirect()->route('admin.learning.show', $program)->with('status', 'Added to the '.$program->year.' L&D plan.');
    }

    public function show(TrainingProgram $program)
    {
        $program->load(['participants.employee', 'needs.employee']);

        return view('admin.learning.show', [
            'program' => $program,
            'employees' => Employee::where('is_active', true)->whereNotIn('id', $program->participants->pluck('employee_id'))->orderBy('last_name')->get(),
            'suggested' => $program->needs->pluck('employee')->filter()->reject(fn ($e) => $program->participants->contains('employee_id', $e->id))->values(),
        ]);
    }

    public function update(Request $request, TrainingProgram $program)
    {
        $data = $this->validated($request) + $request->validate([
            'status' => ['required', Rule::in(['planned', 'scheduled', 'cancelled'])], 'actual_cost' => ['nullable', 'numeric', 'min:0'],
        ]);
        abort_if($program->status === 'done', 422, 'Completed trainings cannot be changed.');
        $program->update($data);
        AuditLog::record('learning.program_updated', $program);

        return back()->with('status', 'Saved.');
    }

    public function destroy(TrainingProgram $program)
    {
        abort_if($program->status === 'done', 422, 'Completed trainings are kept for the records.');
        AuditLog::record('learning.program_deleted', null, ['title' => $program->title]);
        $year = $program->year;
        $program->delete();

        return redirect()->route('admin.learning.index', ['year' => $year])->with('status', 'Removed from the plan.');
    }

    public function nominate(Request $request, TrainingProgram $program)
    {
        $ids = $request->validate(['employee_ids' => ['required', 'array'], 'employee_ids.*' => ['exists:employees,id']])['employee_ids'];
        $added = 0;
        foreach (Employee::whereIn('id', $ids)->get() as $e) {
            $p = TrainingParticipant::firstOrCreate(['training_program_id' => $program->id, 'employee_id' => $e->id], ['status' => 'nominated']);
            if ($p->wasRecentlyCreated) {
                $added++;
                Notify::employee($e, "You are nominated: {$program->title}", [
                    "{$program->title} ({$program->when_label}".($program->hours ? ", {$program->hours} hours" : '').').',
                    $program->provider ? "Provider: {$program->provider}." : 'HR will send the details.',
                ], route('me.learning'));
            }
        }
        if ($program->slots && $program->participants()->count() > $program->slots) {
            session()->flash('warning', 'More nominees than the '.$program->slots.' slots.');
        }
        AuditLog::record('learning.nominated', $program, ['count' => $added]);

        return back()->with('status', "{$added} nominated and notified.");
    }

    public function removeParticipant(TrainingParticipant $participant)
    {
        abort_if($participant->training_id, 422, 'Already recorded on the 201 file.');
        $participant->delete();

        return back()->with('status', 'Removed.');
    }

    /** Marks who attended, then records the training on each attendee's 201 file (PDS L&D). */
    public function complete(Request $request, TrainingProgram $program)
    {
        $data = $request->validate([
            'attended' => ['array'], 'attended.*' => ['integer'],
            'date_from' => ['required', 'date'], 'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'hours' => ['required', 'integer', 'min:1', 'max:2000'], 'actual_cost' => ['nullable', 'numeric', 'min:0'],
        ]);
        abort_if($program->status === 'done', 422);
        $attended = array_map('intval', $data['attended'] ?? []);

        DB::transaction(function () use ($program, $data, $attended) {
            $program->update(['status' => 'done', 'date_from' => $data['date_from'], 'date_to' => $data['date_to'] ?? $data['date_from'],
                'hours' => $data['hours'], 'actual_cost' => $data['actual_cost'] ?? $program->actual_cost]);
            foreach ($program->participants as $p) {
                if (in_array($p->id, $attended, true)) {
                    $training = Training::create(['employee_id' => $p->employee_id, 'title' => $program->title, 'date_from' => $program->date_from,
                        'date_to' => $program->date_to, 'hours' => $program->hours, 'type' => $program->type,
                        'sponsor' => $program->provider ?: Setting::get('agency_name')]);
                    $p->update(['status' => 'attended', 'training_id' => $training->id]);
                } else {
                    $p->update(['status' => 'absent']);
                }
            }
        });
        AuditLog::record('learning.completed', $program, ['attended' => count($attended)]);

        return back()->with('status', count($attended).' attendee(s) recorded on their 201 file (Learning & Development).');
    }

    /** The year's L&D plan, printable. */
    public function print(Request $request)
    {
        $year = $request->integer('year') ?: now()->year;

        return view('print.ld-plan', [
            'year' => $year,
            'programs' => TrainingProgram::withCount('participants')->where('year', $year)->where('status', '!=', 'cancelled')->orderBy('quarter')->orderBy('date_from')->get(),
            'settings' => Setting::allValues(),
        ]);
    }

    /** @return Collection<int, array{employee: Employee, hours: int, count: int}> */
    private function hours(int $year)
    {
        $byEmployee = Training::whereYear('date_from', $year)->get()->groupBy('employee_id');

        return Employee::where('is_active', true)->orderBy('last_name')->get()
            ->map(fn ($e) => ['employee' => $e, 'hours' => (int) ($byEmployee[$e->id] ?? collect())->sum('hours'), 'count' => ($byEmployee[$e->id] ?? collect())->count()])
            ->sortBy('hours')->values();
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'title' => ['required', 'string', 'max:200'], 'competency' => ['nullable', 'string', 'max:200'],
            'type' => ['nullable', Rule::in(array_keys(Training::TYPES))], 'mode' => ['required', Rule::in(array_keys(TrainingProgram::MODES))],
            'provider' => ['nullable', 'string', 'max:200'],
            'date_from' => ['nullable', 'date'], 'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'quarter' => ['nullable', 'integer', 'between:1,4'], 'hours' => ['nullable', 'integer', 'min:1', 'max:2000'],
            'slots' => ['nullable', 'integer', 'min:1', 'max:5000'], 'budget' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
    }
}
