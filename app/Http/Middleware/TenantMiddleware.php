<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\Tenant;

class TenantMiddleware
{
    public function handle($request, Closure $next)
    {
        $tenantId = $request->header('X-Tenant-ID');
        
        // If no tenant ID is provided, use default tenant 1
        if (!$tenantId) {
            $tenantId = 1;
        }
        
        // Validate tenant exists
        $tenant = Tenant::find($tenantId);
        
        // If tenant doesn't exist, return 403 Forbidden
        if (!$tenant) {
            return response()->json([
                'error' => 'Unauthorized: Invalid tenant',
                'message' => 'The tenant ID you provided does not exist'
            ], 403);
        }
        
        $request->headers->set('X-Tenant-ID', $tenantId);
        app()->instance('tenant', $tenant);
        
        return $next($request);
    }
}