<?php

namespace App\Services;

use App\Models\AiProvider;
use App\Support\TenantContext;
use Illuminate\Support\Collection;

/**
 * Resolves the ordered fallback chain of usable AI providers for the current
 * tenant: tenant-scoped enabled providers first (by priority), then
 * platform-scoped enabled providers (by priority).
 */
class ProviderResolver
{
    /**
     * @return Collection<int, AiProvider>
     */
    public function chat(?int $tenantId = null): Collection
    {
        return $this->resolve($tenantId)
            ->filter(fn (AiProvider $p) => ! empty($p->chat_model))
            ->values();
    }

    /**
     * @return Collection<int, AiProvider>
     */
    public function embedding(?int $tenantId = null): Collection
    {
        return $this->resolve($tenantId)
            ->filter(fn (AiProvider $p) => ! empty($p->embedding_model))
            ->values();
    }

    /**
     * Full ordered chain (tenant overrides then platform defaults).
     *
     * @return Collection<int, AiProvider>
     */
    public function resolve(?int $tenantId = null): Collection
    {
        $tenantId = $tenantId ?? TenantContext::tenantId();

        $tenantProviders = collect();
        if ($tenantId !== null) {
            $tenantProviders = AiProvider::query()
                ->where('enabled', true)
                ->where('scope', AiProvider::SCOPE_TENANT)
                ->where('tenant_id', $tenantId)
                ->orderBy('priority')
                ->get();
        }

        $platformProviders = AiProvider::query()
            ->where('enabled', true)
            ->where('scope', AiProvider::SCOPE_PLATFORM)
            ->orderBy('priority')
            ->get();

        return $tenantProviders->concat($platformProviders)->values();
    }

    public function firstChatProvider(?int $tenantId = null): ?AiProvider
    {
        return $this->chat($tenantId)->first();
    }

    public function firstEmbeddingProvider(?int $tenantId = null): ?AiProvider
    {
        return $this->embedding($tenantId)->first();
    }
}
