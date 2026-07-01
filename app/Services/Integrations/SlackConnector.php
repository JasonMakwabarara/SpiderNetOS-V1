<?php

namespace App\Services\Integrations;

use App\Models\Integration;
use Illuminate\Support\Facades\Http;

class SlackConnector implements IntegrationConnector
{
    public function test(Integration $integration): array
    {
        $url = $integration->credentials['webhook_url'] ?? '';
        if ($url === '') {
            return ['ok' => false, 'message' => 'Webhook URL is required.'];
        }

        return ['ok' => true, 'message' => 'Slack webhook configured.'];
    }

    public function send(Integration $integration, string $action, array $payload): array
    {
        $url = $integration->credentials['webhook_url'] ?? '';
        $text = $payload['message'] ?? $payload['text'] ?? 'Notification from SpiderNetOS';

        if ($url === '') {
            return ['ok' => false, 'message' => 'Missing webhook URL.'];
        }

        try {
            $res = Http::timeout(10)->post($url, ['text' => $text]);

            return $res->successful()
                ? ['ok' => true, 'message' => 'Slack message sent.']
                : ['ok' => false, 'message' => 'Slack returned HTTP '.$res->status()];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function sync(Integration $integration): array
    {
        return ['ok' => true, 'message' => 'Slack does not support sync.', 'imported' => 0];
    }
}
