<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * role:employee — the user is linked to an active employee record (any role: HR staff file their own leave too).
 * role:staff    — the user has an HR-office role and uses the admin side.
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        if ($role === 'employee') {
            abort_unless($user->employee !== null, 403);
            abort_unless($user->employee->is_active, 403, 'Your employee record is inactive.');
        } elseif ($role === 'staff') {
            abort_unless($user->isStaff(), 403);
        } else {
            abort_unless($user->role === $role, 403);
        }

        return $next($request);
    }
}
