<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Middleware\AuthenticateKiosk;
use App\Models\AuditLog;
use App\Models\Kiosk;
use Illuminate\Http\Request;

class KioskController extends Controller
{
    public function index()
    {
        return view('admin.kiosks.index', ['kiosks' => Kiosk::orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $kiosk = Kiosk::create($request->validate([
            'name' => ['required', 'string', 'max:100'],
            'location' => ['nullable', 'string', 'max:150'],
        ]));
        AuditLog::record('kiosk.created', $kiosk);

        return back()->with('status', 'Kiosk added. Open this page on the kiosk device and tap "Activate this device".');
    }

    public function update(Request $request, Kiosk $kiosk)
    {
        $kiosk->update($request->validate([
            'name' => ['required', 'string', 'max:100'],
            'location' => ['nullable', 'string', 'max:150'],
        ]) + ['is_active' => $request->boolean('is_active')]);
        AuditLog::record('kiosk.updated', $kiosk);

        return back()->with('status', 'Kiosk updated.');
    }

    public function destroy(Kiosk $kiosk)
    {
        AuditLog::record('kiosk.deleted', $kiosk, ['name' => $kiosk->name]);
        $kiosk->delete();

        return back()->with('status', 'Kiosk removed.');
    }

    /** Turn the current browser into this kiosk. Any previously activated device stops working. */
    public function activate(Kiosk $kiosk)
    {
        $token = $kiosk->issueToken();
        $kiosk->update(['is_active' => true]);
        AuditLog::record('kiosk.activated', $kiosk);

        return redirect()->route('kiosk.show')
            ->withCookie(cookie()->forever(AuthenticateKiosk::COOKIE, $kiosk->id.'|'.$token, null, null, request()->isSecure(), true, false, 'lax'));
    }
}
