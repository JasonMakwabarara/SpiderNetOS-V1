<?php

namespace App\Models\Concerns;

use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Adds a tenant global scope that is derived strictly from the authenticated
 * user (via TenantContext), never from a client header. New tenant-owned models
 * should `use BelongsToTenant;`.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            $tenantId = TenantContext::tenantId();

            // When there is no resolvable tenant (e.g. unauthenticated context),
            // constrain to an impossible id so nothing leaks. Super-admins that
            // need cross-tenant access must call withoutGlobalScope('tenant').
            if ($tenantId === null) {
                if (TenantContext::isSuperAdmin()) {
                    return;
                }
                $builder->whereRaw('1 = 0');

                return;
            }

            $builder->where($builder->getModel()->getTable().'.tenant_id', $tenantId);
        });

        static::creating(function (Model $model) {
            if (empty($model->tenant_id)) {
                $model->tenant_id = TenantContext::tenantId();
            }
        });
    }

    public function tenant()
    {
        return $this->belongsTo(\App\Models\Tenant::class);
    }
}
