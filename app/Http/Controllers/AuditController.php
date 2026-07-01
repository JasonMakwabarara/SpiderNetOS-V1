<?php

namespace App\Http\Controllers;

use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AuditController extends Controller
{
    /**
     * Tenant-scoped audit log from the events table. Super-admins may filter by
     * tenant_id; tenant-admins see only their workspace.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        if (! $user->hasMinimumRole('tenant_admin')) {
            return response()->json(['error' => 'Forbidden: tenant-admin access required'], 403);
        }

        $query = DB::table('events')->orderByDesc('created_at');

        if ($user->isSuperAdmin()) {
            if ($tenantId = $request->query('tenant_id')) {
                $query->where('tenant_id', (int) $tenantId);
            }
        } else {
            $query->where('tenant_id', TenantContext::tenantId());
        }

        if ($type = $request->query('type')) {
            $query->where('type', 'like', $type.'%');
        }

        $limit = min((int) $request->query('limit', 100), 500);

        $rows = $query->limit($limit)->get()->map(function ($row) {
            $row->data = json_decode($row->data ?? 'null', true);

            return $row;
        });

        return response()->json($rows);
    }
}
