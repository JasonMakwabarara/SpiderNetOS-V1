<?php

namespace App\Support;

use Illuminate\Support\Facades\Auth;

/**
 * Resolves the active tenant strictly from the authenticated user.
 *
 * The tenant is NEVER read from a client-supplied header. This is the single
 * source of truth used by the BelongsToTenant global scope and by services
 * such as ProviderResolver.
 */
class TenantContext
{
    /**
     * An explicit tenant id override (used by queued jobs / console where there
     * is no authenticated request). Set via withTenant().
     */
    protected static ?int $overrideTenantId = null;

    public static function tenantId(): ?int
    {
        if (static::$overrideTenantId !== null) {
            return static::$overrideTenantId;
        }

        $user = Auth::user();

        return $user?->tenant_id;
    }

    public static function isSuperAdmin(): bool
    {
        $user = Auth::user();

        return (bool) ($user?->is_super_admin ?? false);
    }

    /**
     * Run a callback with a fixed tenant id (e.g. inside a queued job).
     */
    public static function withTenant(?int $tenantId, callable $callback)
    {
        $previous = static::$overrideTenantId;
        static::$overrideTenantId = $tenantId;

        try {
            return $callback();
        } finally {
            static::$overrideTenantId = $previous;
        }
    }

    public static function setTenantId(?int $tenantId): void
    {
        static::$overrideTenantId = $tenantId;
    }
}
