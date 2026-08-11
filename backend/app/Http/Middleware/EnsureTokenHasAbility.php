<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTokenHasAbility
{
    public function handle(Request $request, Closure $next, string $ability): Response
    {
        if (! $request->user() || ! $request->user()->tokenCan($ability)) {
            abort(403, 'Token perangkat tidak memiliki akses untuk operasi ini.');
        }

        return $next($request);
    }
}
