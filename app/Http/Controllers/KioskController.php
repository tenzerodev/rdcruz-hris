<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesFacePunch;
use App\Services\FaceMatcher;
use App\Services\PunchRecorder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use RuntimeException;

class KioskController extends Controller
{
    use HandlesFacePunch;

    public function show(Request $request)
    {
        return view('kiosk.show', ['kiosk' => $request->attributes->get('kiosk')]);
    }

    /** Fresh CSRF token so a kiosk left open all day never hits "page expired". */
    public function token()
    {
        return response()->json(['token' => csrf_token()]);
    }

    public function punch(Request $request, FaceMatcher $matcher, PunchRecorder $recorder)
    {
        $data = $this->validatePunchRequest($request);
        $kiosk = $request->attributes->get('kiosk');

        $result = $matcher->match($data['descriptor']);
        if (! $result['matched']) {
            return $this->failed('Face not recognized. Please try again or see the HR/admin officer.', 404);
        }

        try {
            $punch = $recorder->record($result['employee'], [
                'source' => 'kiosk',
                'type' => $data['type'] ?? null,
                'kiosk_id' => $kiosk->id,
                'distance' => $result['distance'],
                'liveness' => (bool) ($data['liveness'] ?? false),
                'snapshot' => $data['snapshot'] ?? null,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (RuntimeException $e) {
            return $this->failed($e->getMessage(), 409, ['employee' => ['name' => $result['employee']->display_name]]);
        } catch (UniqueConstraintViolationException) {
            return $this->failed('That punch was just recorded.', 409);
        }

        return $this->punchResponse($punch, $punch->employee->punchLabel($punch->type).' recorded.');
    }
}
