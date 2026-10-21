<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\OfficeRating;
use App\Models\PerformancePeriod;
use App\Models\Setting;
use App\Support\Notify;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * OPCR / DPCR. HR (Performance Management Team) creates them per office and period, approves
 * targets, rates and finalizes; the office or division head fills in targets and accomplishments.
 */
class OfficeRatingController extends Controller
{
    public function index(Request $request)
    {
        $periods = PerformancePeriod::orderByDesc('starts_on')->get();
        $period = $periods->firstWhere('id', $request->integer('period')) ?? $periods->first();

        return view('admin.office-ratings', [
            'periods' => $periods,
            'period' => $period,
            'ratings' => $period ? OfficeRating::with('head')->where('performance_period_id', $period->id)->orderBy('level')->orderBy('office')->get() : collect(),
            'offices' => Employee::where('is_active', true)->whereNotNull('office')->distinct()->orderBy('office')->pluck('office')->filter()->values(),
            'employees' => Employee::where('is_active', true)->orderBy('last_name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->canManage('performance'), 403);
        $data = $request->validate([
            'performance_period_id' => ['required', 'exists:performance_periods,id'],
            'level' => ['required', Rule::in(array_keys(OfficeRating::LEVELS))],
            'office' => ['required', 'string', 'max:150', Rule::unique('office_ratings')->where(fn ($q) => $q->where('performance_period_id', $request->input('performance_period_id'))->where('level', $request->input('level')))],
            'head_id' => ['nullable', 'exists:employees,id'],
        ], ['office.unique' => 'That office already has one for this period.']);
        $rating = OfficeRating::create($data + ['status' => 'draft']);
        AuditLog::record('opcr.created', $rating, $data);
        if ($rating->head) {
            Notify::employee($rating->head, "{$rating->level_name}: set your office targets", ["{$rating->office}, {$rating->period->name}. Enter the office's outputs and success indicators in the HRIS."], route('performance.show', $rating));
        }

        return redirect()->route('performance.show', $rating)->with('status', 'Created. The head can now enter the targets.');
    }

    public function show(Request $request, OfficeRating $rating)
    {
        $user = $request->user();
        abort_unless($user->canSee('performance') || $rating->canEditBy($user), 403);
        $rating->load('items', 'head', 'period');
        $pmt = $user->canManage('performance');
        $isHead = $user->employee && $user->employee->id === $rating->head_id;

        $mode = match (true) {
            $rating->status === 'draft' && ($pmt || $isHead) => 'targets',
            $rating->status === 'targets_approved' && $rating->period->status === 'rating' && ($pmt || $isHead) => 'accomplish',
            default => 'view',
        };

        return view('office-rating', ['rating' => $rating, 'mode' => $mode, 'pmt' => $pmt, 'computed' => $rating->compute(), 'ipcrs' => $rating->ipcrSummary()]);
    }

    /** Targets (draft) or accomplishments and, for HR, Q/E/T ratings. */
    public function save(Request $request, OfficeRating $rating)
    {
        $user = $request->user();
        abort_unless($rating->canEditBy($user), 403);

        if ($rating->status === 'draft') {
            $data = $request->validate([
                'items' => ['required', 'array', 'min:1'],
                'items.*.category' => ['required', Rule::in(array_keys(config('hris.ipcr.categories')))],
                'items.*.output' => ['nullable', 'string', 'max:255'],
                'items.*.success_indicator' => ['nullable', 'string', 'max:2000'],
                'items.*.budget' => ['nullable', 'numeric', 'min:0'],
                'items.*.accountable' => ['nullable', 'string', 'max:200'],
            ]);
            $rows = collect($data['items'])->filter(fn ($r) => filled($r['output'] ?? null) && filled($r['success_indicator'] ?? null))->values();
            DB::transaction(function () use ($rating, $rows) {
                $rating->items()->delete();
                foreach ($rows as $i => $r) {
                    $rating->items()->create($r + ['sort' => $i]);
                }
            });
            AuditLog::record('opcr.targets_saved', $rating, ['items' => $rows->count()]);

            return back()->with('status', 'Targets saved ('.$rows->count().' outputs).');
        }

        abort_unless($rating->status === 'targets_approved', 422);
        $pmt = $user->canManage('performance');
        $data = $request->validate([
            'items' => ['array'], 'items.*.accomplishment' => ['nullable', 'string', 'max:3000'],
            'items.*.q' => ['nullable', 'integer', 'between:1,5'], 'items.*.e' => ['nullable', 'integer', 'between:1,5'], 'items.*.t' => ['nullable', 'integer', 'between:1,5'],
            'items.*.remarks' => ['nullable', 'string', 'max:255'], 'comments' => ['nullable', 'string', 'max:3000'],
        ]);
        foreach ($rating->items as $item) {
            $in = $data['items'][$item->id] ?? [];
            $item->update(['accomplishment' => $in['accomplishment'] ?? $item->accomplishment]
                + ($pmt ? ['q' => $in['q'] ?? null, 'e' => $in['e'] ?? null, 't' => $in['t'] ?? null, 'remarks' => $in['remarks'] ?? null] : []));
        }
        if ($pmt && $request->has('comments')) {
            $rating->update(['comments' => $data['comments']]);
        }
        if ($pmt && $request->boolean('submit')) {
            $computed = $rating->load('items')->compute();
            if ($computed['final'] === null) {
                return back()->with('warning', 'Rate at least one output first.');
            }
            $rating->update(['status' => 'rated', 'rated_at' => now(), 'final_rating' => $computed['final'], 'adjectival' => $computed['adjectival']]);
            AuditLog::record('opcr.rated', $rating, ['rating' => $computed['final']]);

            return back()->with('status', "Rated {$computed['final']} ({$computed['adjectival']}). Finalize it when approved by the head of agency.");
        }
        AuditLog::record('opcr.accomplishments_saved', $rating);

        return back()->with('status', 'Saved.');
    }

    /** HR / PMT: approve targets, finalize, or send back. */
    public function decide(Request $request, OfficeRating $rating)
    {
        abort_unless($request->user()->canManage('performance'), 403);
        $decision = $request->validate(['decision' => ['required', 'in:approve,return,finalize,reopen']])['decision'];
        if ($decision === 'approve') {
            abort_if($rating->status !== 'draft' || $rating->items()->doesntExist(), 422, 'Add the targets first.');
            $rating->update(['status' => 'targets_approved', 'targets_approved_at' => now()]);
        } elseif ($decision === 'return') {
            abort_unless($rating->status === 'targets_approved', 422);
            $rating->update(['status' => 'draft', 'targets_approved_at' => null]);
        } elseif ($decision === 'finalize') {
            abort_unless($rating->status === 'rated', 422);
            $rating->update(['status' => 'final', 'finalized_at' => now()]);
        } else {
            abort_unless(in_array($rating->status, ['rated', 'final'], true), 422);
            $rating->update(['status' => 'targets_approved', 'rated_at' => null, 'finalized_at' => null, 'final_rating' => null, 'adjectival' => null]);
        }
        AuditLog::record('opcr.'.$decision, $rating);
        if ($rating->head && in_array($decision, ['approve', 'return', 'finalize'], true)) {
            Notify::employee($rating->head, "{$rating->level_name} {$rating->office}: ".['approve' => 'targets approved', 'return' => 'targets returned', 'finalize' => 'final rating'][$decision],
                [$decision === 'finalize' ? "Final rating: {$rating->final_rating} ({$rating->adjectival})." : $rating->period->name.'.'], route('performance.show', $rating));
        }

        return back()->with('status', ['approve' => 'Targets approved.', 'return' => 'Returned to the head for revision.', 'finalize' => 'Final.', 'reopen' => 'Reopened for rating.'][$decision]);
    }

    public function destroy(Request $request, OfficeRating $rating)
    {
        abort_unless($request->user()->canManage('performance') && $rating->status !== 'final', 403);
        AuditLog::record('opcr.deleted', null, ['office' => $rating->office, 'level' => $rating->level]);
        $period = $rating->performance_period_id;
        $rating->delete();

        return redirect()->route('admin.office-ratings.index', ['period' => $period])->with('status', 'Deleted.');
    }

    public function print(Request $request, OfficeRating $rating)
    {
        abort_unless($request->user()->canSee('performance') || $rating->canEditBy($request->user()), 403);

        return view('print.office-rating', ['rating' => $rating->load('items', 'head', 'period'), 'computed' => $rating->compute(), 'settings' => Setting::allValues()]);
    }
}
