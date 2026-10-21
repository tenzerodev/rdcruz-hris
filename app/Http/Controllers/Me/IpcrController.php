<?php

namespace App\Http\Controllers\Me;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Ipcr;
use App\Models\OfficeRating;
use App\Models\PerformancePeriod;
use App\Support\Notify;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * SPMS / IPCR workflow:
 * employee drafts targets → rater approves (or returns) → in the rating phase the employee enters
 * accomplishments and self-ratings → rater gives Q/E/T ratings → HR finalizes.
 */
class IpcrController extends Controller
{
    public function index(Request $request)
    {
        $me = $request->user()->employee;

        return view('me.ipcr-index', [
            'mine' => $me->ipcrs()->with('period')->latest('id')->get(),
            'current' => PerformancePeriod::current(),
            'team' => $request->user()->isSupervisor()
                ? Ipcr::with('employee', 'period')->whereIn('employee_id', $me->subordinates()->pluck('id'))
                    ->whereHas('period', fn ($q) => $q->where('status', '!=', 'closed'))->get()
                : collect(),
            'offices' => OfficeRating::with('period')->where('head_id', $me->id)->latest('id')->get(),
        ]);
    }

    public function start(Request $request, PerformancePeriod $period)
    {
        abort_if($period->status === 'closed', 422, 'This rating period is closed.');
        $me = $request->user()->employee;
        $ipcr = Ipcr::firstOrCreate(['employee_id' => $me->id, 'performance_period_id' => $period->id], ['rater_id' => $me->supervisor_id, 'status' => 'draft']);

        return redirect()->route('me.ipcr.show', $ipcr);
    }

    public function show(Request $request, Ipcr $ipcr)
    {
        $user = $request->user();
        abort_unless($user->canView($ipcr->employee), 403);
        $ipcr->load('items', 'employee', 'rater', 'period');
        $owner = $user->employee?->id === $ipcr->employee_id;
        $rater = $user->canActFor($ipcr->employee) && ! $owner;

        $mode = match (true) {
            $owner && $ipcr->status === 'draft' => 'targets',
            $owner && $ipcr->status === 'targets_approved' && $ipcr->period->status === 'rating' => 'self',
            $rater && $ipcr->status === 'targets_submitted' => 'review_targets',
            $rater && $ipcr->status === 'self_rated' => 'rate',
            default => 'view',
        };

        return view('me.ipcr-show', ['ipcr' => $ipcr, 'mode' => $mode, 'self' => $ipcr->compute(true), 'rating' => $ipcr->compute()]);
    }

    /** Employee: save targets (draft) or accomplishments + self-ratings (rating phase). Optionally submit. */
    public function save(Request $request, Ipcr $ipcr)
    {
        abort_unless($request->user()->employee?->id === $ipcr->employee_id, 403);
        $submit = $request->boolean('submit');

        if ($ipcr->status === 'draft') {
            $data = $request->validate([
                'items' => ['array'],
                'items.*.category' => ['required', Rule::in(array_keys(config('hris.ipcr.categories')))],
                'items.*.output' => ['required', 'string', 'max:250'],
                'items.*.success_indicator' => ['required', 'string', 'max:1000'],
            ]);
            $items = array_values($data['items'] ?? []);
            if ($submit && ! $items) {
                throw ValidationException::withMessages(['items' => 'Add at least one output before submitting.']);
            }
            DB::transaction(function () use ($ipcr, $items) {
                $ipcr->items()->delete();
                foreach ($items as $i => $item) {
                    $ipcr->items()->create($item + ['sort' => $i]);
                }
            });
            if ($submit) {
                $ipcr->update(['status' => 'targets_submitted', 'rater_id' => $ipcr->rater_id ?? $ipcr->employee->supervisor_id]);
                AuditLog::record('ipcr.targets_submitted', $ipcr);
                Notify::approver($ipcr->employee, "IPCR targets for approval: {$ipcr->employee->display_name}", ["{$ipcr->period->name}: {$ipcr->items()->count()} output(s) submitted."], route('me.ipcr.show', $ipcr));
            }

            return redirect()->route('me.ipcr.show', $ipcr)->with('status', $submit ? 'Targets submitted to your rater.' : 'Draft saved.');
        }

        abort_unless($ipcr->status === 'targets_approved' && $ipcr->period->status === 'rating', 422, 'This IPCR is not open for your accomplishments.');
        $rating = ['nullable', 'integer', 'between:1,5'];
        $data = $request->validate([
            'items' => ['required', 'array'],
            'items.*.accomplishment' => [$submit ? 'required' : 'nullable', 'string', 'max:2000'],
            'items.*.self_q' => $rating, 'items.*.self_e' => $rating, 'items.*.self_t' => $rating,
        ], ['items.*.accomplishment.required' => 'Enter the actual accomplishment for every output.']);
        foreach ($ipcr->items as $item) {
            $item->update(array_intersect_key($data['items'][$item->id] ?? [], array_flip(['accomplishment', 'self_q', 'self_e', 'self_t'])));
        }
        if ($submit) {
            $ipcr->update(['status' => 'self_rated']);
            AuditLog::record('ipcr.self_rated', $ipcr);
            Notify::approver($ipcr->employee, "IPCR for rating: {$ipcr->employee->display_name}", ["{$ipcr->period->name}: accomplishments and self-rating submitted."], route('me.ipcr.show', $ipcr));
        }

        return redirect()->route('me.ipcr.show', $ipcr)->with('status', $submit ? 'Submitted to your rater.' : 'Saved.');
    }

    /** Rater: approve or return targets; or give the final Q/E/T ratings. */
    public function review(Request $request, Ipcr $ipcr)
    {
        $user = $request->user();
        abort_unless($user->canActFor($ipcr->employee) && $user->employee?->id !== $ipcr->employee_id, 403);

        if ($ipcr->status === 'targets_submitted') {
            $data = $request->validate(['decision' => ['required', 'in:approve,return'], 'comments' => ['nullable', 'required_if:decision,return', 'string', 'max:2000']]);
            $approve = $data['decision'] === 'approve';
            $ipcr->update($approve
                ? ['status' => 'targets_approved', 'targets_approved_at' => now(), 'rater_id' => $user->employee?->id ?? $ipcr->rater_id]
                : ['status' => 'draft', 'comments' => $data['comments']]);
            AuditLog::record($approve ? 'ipcr.targets_approved' : 'ipcr.targets_returned', $ipcr);
            Notify::employee($ipcr->employee, $approve ? 'Your IPCR targets are approved' : 'Your IPCR targets were returned for revision',
                [$ipcr->period->name.'.', $approve ? 'You can enter accomplishments when the rating phase opens.' : 'Comments: '.$data['comments']], route('me.ipcr.show', $ipcr));

            return redirect()->route('me.ipcr')->with('status', $approve ? 'Targets approved.' : 'Returned for revision.');
        }

        abort_unless($ipcr->status === 'self_rated', 422, 'This IPCR is not waiting for your rating.');
        $r = ['nullable', 'integer', 'between:1,5'];
        $data = $request->validate([
            'items' => ['required', 'array'], 'items.*.q' => $r, 'items.*.e' => $r, 'items.*.t' => $r, 'items.*.remarks' => ['nullable', 'string', 'max:250'],
            'comments' => ['nullable', 'string', 'max:2000'], 'development_plan' => ['nullable', 'string', 'max:2000'],
        ]);
        foreach ($ipcr->items as $item) {
            $in = $data['items'][$item->id] ?? [];
            if (empty($in['q']) && empty($in['e']) && empty($in['t'])) {
                throw ValidationException::withMessages(['items' => "Rate at least one of Q, E or T for “{$item->output}”."]);
            }
            $item->update(array_intersect_key($in, array_flip(['q', 'e', 't', 'remarks'])));
        }
        $result = $ipcr->fresh('items')->compute();
        $ipcr->update(['status' => 'rated', 'rated_at' => now(), 'final_rating' => $result['final'], 'adjectival' => $result['adjectival'],
            'comments' => $data['comments'] ?? null, 'development_plan' => $data['development_plan'] ?? null, 'rater_id' => $user->employee?->id ?? $ipcr->rater_id]);
        AuditLog::record('ipcr.rated', $ipcr, $result);
        Notify::employee($ipcr->employee, 'Your IPCR has been rated', ["{$ipcr->period->name}: {$result['final']} ({$result['adjectival']})."], route('me.ipcr.show', $ipcr));
        Notify::hr("IPCR rated: {$ipcr->employee->display_name}", ["{$ipcr->period->name}: {$result['final']} ({$result['adjectival']}). Ready for finalization."], route('admin.ipcr.index', ['period' => $ipcr->performance_period_id]));

        return redirect()->route('me.ipcr')->with('status', "Rated {$result['final']} — {$result['adjectival']}.");
    }
}
