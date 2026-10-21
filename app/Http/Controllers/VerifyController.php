<?php

namespace App\Http\Controllers;

use App\Models\DocumentVerification;
use Illuminate\Http\Request;

/** Public page: anyone holding a printout can check it is genuine and current. */
class VerifyController extends Controller
{
    public function form(Request $request)
    {
        if ($code = $request->query('code')) {
            return redirect()->route('verify.show', strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code)));
        }

        return view('verify.show', ['doc' => null, 'searched' => false]);
    }

    public function show(string $code)
    {
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code));
        $doc = DocumentVerification::where('code', $code)->first();
        if ($doc) {
            $doc->forceFill(['views' => $doc->views + 1, 'last_viewed_at' => now()])->saveQuietly();
        }

        return response()->view('verify.show', ['doc' => $doc, 'searched' => true, 'code' => $code, 'newer' => $doc?->newer()], $doc ? 200 : 404);
    }
}
