<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/** The bell: notices for the signed-in user. */
class NotificationController extends Controller
{
    public function index(Request $request)
    {
        return view('notifications', [
            'notifications' => $request->user()->notifications()->paginate(30),
        ]);
    }

    /** Marks one notice read and goes to what it is about. */
    public function open(Request $request, string $id)
    {
        $notice = $request->user()->notifications()->findOrFail($id);
        $notice->markAsRead();
        $url = $notice->data['url'] ?? null;

        return $url && str_starts_with($url, url('/')) ? redirect($url) : redirect()->route('notifications');
    }

    public function readAll(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('status', 'All marked as read.');
    }

    public function preferences(Request $request)
    {
        $request->user()->update(['notify_email' => $request->boolean('notify_email')]);

        return back()->with('status', $request->boolean('notify_email') ? 'You will also get notices by email.' : 'Notices will only show here in the HRIS.');
    }
}
