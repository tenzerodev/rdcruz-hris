<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * area:people — reading needs view access to the area, changing anything needs manage access.
 * Read-only users see a banner on the page.
 */
class EnsureArea
{
    public function handle(Request $request, Closure $next, string $area): Response
    {
        $user = $request->user();
        $reading = $request->isMethodSafe();

        abort_unless($user && ($reading ? $user->canSee($area) : $user->canManage($area)), 403,
            $reading ? 'Your role does not have access to this page.' : 'Your role can view this page but not change it.');

        View::share('readOnly', ! $user->canManage($area));

        return $next($request);
    }
}
