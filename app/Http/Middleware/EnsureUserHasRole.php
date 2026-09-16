<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        if (! $request->user() || ! in_array($request->user()->role, $roles, true)) {
            abort(403, 'You are not authorized to access this area.');
        }

        return $next($request);
    }
}
