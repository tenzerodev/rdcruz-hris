<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminCase;
use App\Models\AuditLog;
use App\Models\CaseEvent;
use App\Models\CaseSuspension;
use App\Models\Employee;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Administrative cases: complaint to decision, with a dated record of every step and document. */
class CaseController extends Controller
{
    public function index(Request $request)
    {
        $filter = $request->query('filter', 'open');
        $q = trim((string) $request->query('q'));

        return view('admin.cases.index', [
            'filter' => $filter,
            'cases' => AdminCase::with('employee')
                ->when($filter === 'open', fn ($w) => $w->whereNotIn('status', ['decided', 'dismissed', 'closed']))
                ->when($filter === 'closed', fn ($w) => $w->whereIn('status', ['decided', 'dismissed', 'closed']))
                ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('case_no', 'like', "%$q%")->orWhere('offense', 'like', "%$q%")
                    ->orWhereHas('employee', fn ($e) => $e->where('last_name', 'like', "%$q%")->orWhere('first_name', 'like', "%$q%"))))
                ->latest('date_filed')->paginate(30)->withQueryString(),
            'due' => CaseEvent::with('adminCase.employee')->whereNotNull('due_on')->whereDate('due_on', '<=', today()->addDays(7))
                ->whereHas('adminCase', fn ($c) => $c->whereNotIn('status', ['decided', 'dismissed', 'closed']))->orderBy('due_on')->get(),
            'employees' => Employee::orderBy('last_name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'case_no' => ['nullable', 'string', 'max:40', 'unique:admin_cases,case_no'],
            'complainant' => ['nullable', 'string', 'max:200'],
            'offense' => ['required', 'string', 'max:200'],
            'classification' => ['nullable', Rule::in(array_keys(AdminCase::CLASSIFICATIONS))],
            'description' => ['nullable', 'string', 'max:5000'],
            'date_filed' => ['required', 'date', 'before_or_equal:today'],
        ]);
        $data['case_no'] ??= $this->nextCaseNo();
        $case = AdminCase::create($data + ['status' => 'complaint', 'created_by' => $request->user()->id]);
        $case->events()->create(['date' => $data['date_filed'], 'type' => 'complaint', 'details' => $data['complainant'] ? 'Filed by '.$data['complainant'] : null, 'created_by' => $request->user()->id]);
        AuditLog::record('case.created', $case, ['case_no' => $case->case_no]);

        return redirect()->route('admin.cases.show', $case)->with('status', "Case {$case->case_no} opened.");
    }

    public function show(AdminCase $case)
    {
        $case->load('employee', 'events', 'suspensions');
        AuditLog::record('case.viewed', $case);

        return view('admin.cases.show', ['case' => $case, 'others' => AdminCase::where('employee_id', $case->employee_id)->whereKeyNot($case->id)->latest('date_filed')->get()]);
    }

    public function update(Request $request, AdminCase $case)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(AdminCase::STATUSES))],
            'complainant' => ['nullable', 'string', 'max:200'], 'offense' => ['required', 'string', 'max:200'],
            'classification' => ['nullable', Rule::in(array_keys(AdminCase::CLASSIFICATIONS))], 'description' => ['nullable', 'string', 'max:5000'],
        ]);
        if ($data['status'] === 'decided' && ! $case->decided_on) {
            throw ValidationException::withMessages(['status' => 'Record the decision below; that marks the case decided.']);
        }
        $case->update($data + (in_array($data['status'], ['dismissed', 'closed'], true) && ! $case->closed_on ? ['closed_on' => today()] : []));
        AuditLog::record('case.updated', $case, ['status' => $data['status']]);

        return back()->with('status', 'Saved. Status: '.$case->status_label.'.');
    }

    public function storeEvent(Request $request, AdminCase $case)
    {
        $data = $request->validate([
            'date' => ['required', 'date'], 'type' => ['required', Rule::in(array_keys(CaseEvent::TYPES))],
            'details' => ['nullable', 'string', 'max:3000'], 'due_on' => ['nullable', 'date', 'after_or_equal:date'],
            'document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:15360'],
        ]);
        $event = $case->events()->create(collect($data)->except('document')->all() + ['created_by' => $request->user()->id]);
        if ($file = $request->file('document')) {
            $event->update(['document_path' => $file->store("cases/{$case->id}", 'local'), 'document_name' => mb_substr($file->getClientOriginalName(), 0, 200)]);
        }
        // Moving forward in the process moves the status with it.
        $next = ['show_cause' => 'preliminary', 'formal_charge' => 'formal_charge', 'pre_hearing' => 'hearing', 'hearing' => 'hearing', 'report' => 'for_decision', 'appeal' => 'appealed'][$data['type']] ?? null;
        if ($next && $case->isOpen()) {
            $case->update(['status' => $next]);
        } elseif ($data['type'] === 'appeal') {
            $case->update(['status' => 'appealed']);
        }
        AuditLog::record('case.event_added', $case, ['type' => $data['type']]);

        return back()->with('status', CaseEvent::TYPES[$data['type']].' recorded.');
    }

    public function destroyEvent(CaseEvent $event)
    {
        if ($event->document_path) {
            Storage::disk('local')->delete($event->document_path);
        }
        AuditLog::record('case.event_deleted', $event->adminCase, ['type' => $event->type]);
        $event->delete();

        return back()->with('status', 'Removed.');
    }

    public function document(CaseEvent $event)
    {
        abort_unless($event->document_path && Storage::disk('local')->exists($event->document_path), 404);
        AuditLog::record('case.document_viewed', $event->adminCase, ['event' => $event->id]);

        return Storage::disk('local')->response($event->document_path, $event->document_name);
    }

    public function decide(Request $request, AdminCase $case)
    {
        $data = $request->validate([
            'penalty' => ['required', Rule::in(array_keys(AdminCase::PENALTIES))],
            'decision' => ['required', 'string', 'max:5000'],
            'decided_on' => ['required', 'date'],
            'suspension_from' => ['nullable', 'required_if:penalty,suspension', 'date'],
            'suspension_to' => ['nullable', 'required_if:penalty,suspension', 'date', 'after_or_equal:suspension_from'],
        ]);
        $case->update(['penalty' => $data['penalty'], 'decision' => $data['decision'], 'decided_on' => $data['decided_on'], 'status' => 'decided']);
        $case->events()->create(['date' => $data['decided_on'], 'type' => 'decision', 'details' => AdminCase::PENALTIES[$data['penalty']].'. '.$data['decision'], 'created_by' => $request->user()->id]);
        if ($data['penalty'] === 'suspension') {
            $case->suspensions()->create(['employee_id' => $case->employee_id, 'kind' => 'penalty', 'date_from' => $data['suspension_from'], 'date_to' => $data['suspension_to']]);
        }
        AuditLog::record('case.decided', $case, ['penalty' => $data['penalty']]);

        return back()->with('status', 'Decision recorded.'.($data['penalty'] === 'suspension' ? ' The suspension days show on the DTR and are deducted from pay.' : '').($data['penalty'] === 'dismissal' ? ' Start the clearance when the decision is final and executory.' : ''));
    }

    public function storeSuspension(Request $request, AdminCase $case)
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(array_keys(CaseSuspension::KINDS))],
            'date_from' => ['required', 'date'], 'date_to' => ['required', 'date', 'after_or_equal:date_from'],
        ]);
        if ($data['kind'] === 'preventive' && now()->parse($data['date_from'])->diffInDays(now()->parse($data['date_to'])) + 1 > 90) {
            throw ValidationException::withMessages(['date_to' => 'Preventive suspension is up to 90 days.']);
        }
        $case->suspensions()->create($data + ['employee_id' => $case->employee_id, 'with_pay' => $request->boolean('with_pay')]);
        AuditLog::record('case.suspension_added', $case, $data);

        return back()->with('status', CaseSuspension::KINDS[$data['kind']].' recorded.');
    }

    /** E.g. pay restored after exoneration: the preventive suspension becomes with pay. */
    public function updateSuspension(Request $request, CaseSuspension $suspension)
    {
        $suspension->update(['with_pay' => $request->boolean('with_pay')]);
        AuditLog::record('case.suspension_updated', $suspension->adminCase, ['with_pay' => $suspension->with_pay]);

        return back()->with('status', $suspension->with_pay ? 'Marked with pay. Recompute any draft payroll.' : 'Marked without pay.');
    }

    public function destroySuspension(CaseSuspension $suspension)
    {
        AuditLog::record('case.suspension_deleted', $suspension->adminCase);
        $suspension->delete();

        return back()->with('status', 'Removed.');
    }

    public function print(AdminCase $case)
    {
        AuditLog::record('case.printed', $case);

        return view('print.admin-case', ['case' => $case->load('employee', 'events', 'suspensions'), 'settings' => Setting::allValues()]);
    }

    private function nextCaseNo(): string
    {
        $prefix = 'AC-'.now()->year.'-';
        $n = AdminCase::where('case_no', 'like', $prefix.'%')->count() + 1;
        while (AdminCase::where('case_no', $prefix.str_pad($n, 3, '0', STR_PAD_LEFT))->exists()) {
            $n++;
        }

        return $prefix.str_pad($n, 3, '0', STR_PAD_LEFT);
    }
}
