<?php

namespace App\Http\Controllers;

use App\Models\Setting;

/** Public privacy notice (RA 10173), linked from sign-in, kiosks and face enrollment. */
class PrivacyController extends Controller
{
    public function show()
    {
        return view('privacy', ['s' => Setting::allValues()]);
    }
}
