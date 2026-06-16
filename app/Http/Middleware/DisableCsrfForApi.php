<?php

namespace App\Http\Middleware;

use Closure;

class DisableCsrfForApi
{
    public function handle($request, Closure $next)
    {
        if ($request->is('api/*') || $request->is('login')) {
            return $next($request);
        }
        return $next($request);
    }
}