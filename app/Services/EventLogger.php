<?php

namespace App\Services;

use App\Support\TenantContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Appends an audit/domain event to the `events` table. Tenant and user are
 * derived from the current authenticated context. Used for audit logging
 * (Phase 8) and domain events.
 */
class EventLogger
{
    public static function log(string $type, string $aggregateId = '', array $data = []): void
    {
        DB::table('events')->insert([
            'id' => (string) Str::uuid(),
            'type' => $type,
            'aggregate_id' => $aggregateId,
            'data' => json_encode($data),
            'tenant_id' => TenantContext::tenantId(),
            'user_id' => Auth::id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
