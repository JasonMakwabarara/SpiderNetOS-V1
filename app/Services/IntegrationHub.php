<?php

namespace App\Services;

use App\Models\Integration;
use App\Services\Integrations\EmailConnector;
use App\Services\Integrations\HubSpotConnector;
use App\Services\Integrations\IntegrationConnector;
use App\Services\Integrations\SalesforceConnector;
use App\Services\Integrations\SlackConnector;
use App\Services\Integrations\WhatsAppConnector;
use App\Support\TenantBroadcast;
use App\Support\TenantContext;

/**
 * Normalized facade over external connectors. Used by the Integrations API,
 * flow nodes, and future inbound webhooks.
 */
class IntegrationHub
{
    public function connector(Integration $integration): IntegrationConnector
    {
        return match ($integration->type) {
            Integration::TYPE_SLACK => app(SlackConnector::class),
            Integration::TYPE_EMAIL => app(EmailConnector::class),
            Integration::TYPE_WHATSAPP => app(WhatsAppConnector::class),
            Integration::TYPE_HUBSPOT => app(HubSpotConnector::class),
            Integration::TYPE_SALESFORCE => app(SalesforceConnector::class),
            default => throw new \InvalidArgumentException("Unknown integration type: {$integration->type}"),
        };
    }

    public function test(Integration $integration): array
    {
        $result = $this->connector($integration)->test($integration);
        $integration->update([
            'status' => $result['ok'] ? Integration::STATUS_CONNECTED : Integration::STATUS_ERROR,
            'last_error' => $result['ok'] ? null : $result['message'],
        ]);

        $this->broadcastStatus($integration, $result['message'] ?? null);

        return $result;
    }

    public function send(Integration $integration, string $action, array $payload): array
    {
        $result = $this->connector($integration)->send($integration, $action, $payload);
        if (! $result['ok']) {
            $integration->update(['status' => Integration::STATUS_ERROR, 'last_error' => $result['message']]);
        }

        return $result;
    }

    public function sync(Integration $integration): array
    {
        $result = $this->connector($integration)->sync($integration);
        $integration->update([
            'last_synced_at' => now(),
            'status' => $result['ok'] ? Integration::STATUS_CONNECTED : Integration::STATUS_ERROR,
            'last_error' => $result['ok'] ? null : ($result['message'] ?? 'Sync failed'),
        ]);

        $this->broadcastStatus($integration, $result['message'] ?? null);

        return $result;
    }

    protected function broadcastStatus(Integration $integration, ?string $message = null): void
    {
        $tenantId = TenantContext::tenantId() ?? $integration->tenant_id;
        if ($tenantId) {
            TenantBroadcast::integration($tenantId, $integration->id, $integration->status, $message);
        }
    }
}
