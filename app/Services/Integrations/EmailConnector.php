<?php

namespace App\Services\Integrations;

use App\Jobs\SendEmailJob;
use App\Models\Integration;

class EmailConnector implements IntegrationConnector
{
    public function test(Integration $integration): array
    {
        $from = $integration->credentials['from'] ?? $integration->credentials['username'] ?? '';
        if ($from === '') {
            return ['ok' => false, 'message' => 'From address or username is required.'];
        }

        return ['ok' => true, 'message' => 'Email integration configured.'];
    }

    public function send(Integration $integration, string $action, array $payload): array
    {
        $to = $payload['to'] ?? $integration->config['default_to'] ?? '';
        if ($to === '') {
            return ['ok' => false, 'message' => 'Recipient (to) is required.'];
        }

        SendEmailJob::dispatch([
            'to' => $to,
            'subject' => $payload['subject'] ?? 'SpiderNetOS notification',
            'body' => $payload['body'] ?? $payload['message'] ?? '',
            'integration_id' => $integration->id,
        ]);

        return ['ok' => true, 'message' => "Email queued to {$to}."];
    }

    public function sync(Integration $integration): array
    {
        return ['ok' => true, 'message' => 'Inbound email sync is not enabled yet.', 'imported' => 0];
    }
}
