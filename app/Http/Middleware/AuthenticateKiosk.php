<?php

namespace App\Http\Middleware;

use App\Models\Kiosk;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Allows only devices activated by an admin (long-lived encrypted cookie holding "id|token"). */
class AuthenticateKiosk
{
    public const COOKIE = 'dtr_kiosk';

    public function handle(Request $request, Closure $next): Response
    {
        [$id, $token] = array_pad(explode('|', (string) $request->cookie(self::COOKIE), 2), 2, '');
        $kiosk = ctype_digit($id) ? Kiosk::find($id) : null;

        if (! $kiosk || ! $kiosk->is_active || ! $kiosk->tokenMatches($token)) {
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'message' => 'This device is not an activated kiosk.'], 403);
            }

            return response()->view('kiosk.inactive', [], 403);
        }

        if (! $kiosk->last_seen_at || $kiosk->last_seen_at->lt(now()->subMinutes(5))) {
            $kiosk->forceFill(['last_seen_at' => now()])->saveQuietly();
        }

        $request->attributes->set('kiosk', $kiosk);

        return $next($request);
    }
}
