<?php

namespace App\Http\Controllers\Me;

use App\Http\Controllers\Controller;
use App\Models\TrainingNeed;
use App\Models\TrainingParticipant;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** The employee's trainings: hours this year, nominations, and the learning needs survey. */
class LearningController extends Controller
{
    public function index(Request $request)
    {
        $e = $request->user()->employee;
        $year = now()->year;

        return view('me.learning', [
            'year' => $year,
            'trainings' => $e->trainings()->limit(50)->get(),
            'hours' => (int) $e->trainings()->whereYear('date_from', $year)->sum('hours'),
            'target' => (int) config('hris.learning.target_hours', 40),
            'nominations' => TrainingParticipant::with('program')->where('employee_id', $e->id)->where('status', 'nominated')
                ->whereHas('program', fn ($q) => $q->whereIn('status', ['planned', 'scheduled']))->get(),
            'needs' => TrainingNeed::with('program')->where('employee_id', $e->id)->where('year', '>=', $year)->latest()->get(),
        ]);
    }

    public function storeNeed(Request $request)
    {
        $data = $request->validate([
            'year' => ['required', 'integer', Rule::in([now()->year, now()->year + 1])],
            'competency' => ['required', 'string', 'max:200'], 'training_title' => ['nullable', 'string', 'max:200'],
            'reason' => ['nullable', 'string', 'max:500'], 'priority' => ['required', Rule::in(array_keys(TrainingNeed::PRIORITIES))],
        ]);
        TrainingNeed::create($data + ['employee_id' => $request->user()->employee->id]);

        return back()->with('status', 'Thank you. HR uses these to plan next year\'s trainings.');
    }

    public function destroyNeed(Request $request, TrainingNeed $need)
    {
        abort_unless($need->employee_id === $request->user()->employee->id && ! $need->training_program_id, 403);
        $need->delete();

        return back()->with('status', 'Removed.');
    }
}
