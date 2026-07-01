<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\TenantContext;
use Closure;
use App\Services\EventLogger;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Establishes the tenant context strictly from the authenticated user.
 *
 * The tenant is NEVER trusted from a client header. If a client sends an
 * X-Tenant-ID that does not match the authenticated user's tenant, the request
 * is rejected. Unauthenticated requests (e.g. /login) pass through untouched;
 * route-level auth middleware handles access control.
 */
class TenantMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            $tenantId = $user->tenant_id;

            // Reject spoofed/ mismatched client-supplied tenant headers.
            $header = $request->header('X-Tenant-ID');
            if ($header !== null && (int) $header !== (int) $tenantId) {
                EventLogger::log('security.tenant_mismatch', (string) $user->id, [
                    'header' => $header,
                    'actual' => $tenantId,
                    'ip' => $request->ip(),
                ]);

                return response()->json([
                    'error' => 'Forbidden: tenant mismatch',
                ], 403);
            }

            TenantContext::setTenantId($tenantId ? (int) $tenantId : null);

            if ($tenantId) {
                app()->instance('tenant', Tenant::find($tenantId));
            }
        }

        return $next($request);
    }
}
