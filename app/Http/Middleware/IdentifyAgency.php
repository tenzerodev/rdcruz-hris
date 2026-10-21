<?php

namespace App\Http\Middleware;

use App\Support\Agencies;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Multi-agency mode: picks the agency from the web address before anything touches the database. */
class IdentifyAgency
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Agencies::enabled() || $request->is('up')) {
            return $next($request);
        }
        $agency = Agencies::forHost($request->getHost());
        abort_unless($agency && empty($agency['disabled']), 404, 'This address is not set up for any agency.');
        Agencies::activate($agency);

        return $next($request);
    }
}
