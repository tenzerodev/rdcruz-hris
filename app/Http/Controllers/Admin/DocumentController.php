<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\DocumentVerification;
use Illuminate\Http\Request;

/** HR's list of issued (printed) documents, with revoke. */
class DocumentController extends Controller
{
    public function index(Request $request)
    {
        $q = strtoupper(preg_replace('/[^A-Za-z0-9 ]/', '', (string) $request->query('q')));

        return view('admin.documents', [
            'q' => $q,
            'docs' => DocumentVerification::with('employee', 'issuer:id,name')
                ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('code', str_replace(' ', '', $q))->orWhere('title', 'like', "%$q%")))
                ->when($request->query('type'), fn ($w, $t) => $w->where('type', $t))
                ->latest()->paginate(40)->withQueryString(),
        ]);
    }

    public function revoke(Request $request, DocumentVerification $document)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:200']]);
        $document->update(['revoked_at' => now(), 'revoked_reason' => $data['reason']]);
        AuditLog::record('document.revoked', $document, ['code' => $document->code, 'reason' => $data['reason']]);

        return back()->with('status', "Document {$document->display_code} revoked. The verification page now shows it as not valid.");
    }
}
