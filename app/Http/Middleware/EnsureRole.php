<?php

namespace App\Http\Middleware;

use App\Support\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Requires the authenticated user to hold one of the listed roles (or higher).
 * Super-admins always pass.
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        $minimum = collect($roles)
            ->map(fn (string $role) => UserRole::rank($role))
            ->max() ?? UserRole::rank(UserRole::MEMBER);

        if (UserRole::rank($user->effectiveRole()) >= $minimum) {
            return $next($request);
        }

        return response()->json(['error' => 'Forbidden: insufficient permissions'], 403);
    }
}
