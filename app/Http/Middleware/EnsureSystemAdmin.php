<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates the /api/v1/admin/* routes (FR-16.x). Registered under the
 * 'system_admin' alias in bootstrap/app.php — must run after 'auth:sanctum'
 * in the route's middleware list so $request->user() is populated.
 */
class EnsureSystemAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || ! $request->user()->isSystemAdmin()) {
            abort(403, 'This action requires system administrator access.');
        }

        return $next($request);
    }
}
