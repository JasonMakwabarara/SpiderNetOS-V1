<?php

namespace App\Support;

use App\Events\AgentRunStatusChanged;
use App\Events\FlowRunStatusChanged;
use App\Events\IntegrationStatusChanged;
use App\Events\MonitoringMetricsUpdated;
use App\Services\MonitoringService;

/**
 * Thin helper for tenant-scoped WebSocket broadcasts (Laravel Reverb).
 */
class TenantBroadcast
{
    public static function monitoringSnapshot(?int $tenantId = null): void
    {
        if (! static::enabled()) {
            return;
        }

        $tenantId ??= TenantContext::tenantId();
        if (! $tenantId) {
            return;
        }

        $metrics = app(MonitoringService::class)->snapshot();
        broadcast(new MonitoringMetricsUpdated($tenantId, $metrics));
    }

    public static function flowRun(int $tenantId, int $flowRunId, int $flowId, string $status): void
    {
        if (! static::enabled()) {
            return;
        }

        broadcast(new FlowRunStatusChanged($tenantId, $flowRunId, $flowId, $status));
        static::monitoringSnapshot($tenantId);
    }

    public static function agentRun(int $tenantId, int $agentRunId, int $agentId, string $status): void
    {
        if (! static::enabled()) {
            return;
        }

        broadcast(new AgentRunStatusChanged($tenantId, $agentRunId, $agentId, $status));
        static::monitoringSnapshot($tenantId);
    }

    public static function integration(int $tenantId, int $integrationId, string $status, ?string $message = null): void
    {
        if (! static::enabled()) {
            return;
        }

        broadcast(new IntegrationStatusChanged($tenantId, $integrationId, $status, $message));
        static::monitoringSnapshot($tenantId);
    }

    protected static function enabled(): bool
    {
        $driver = config('broadcasting.default');

        return $driver && $driver !== 'null';
    }
}
