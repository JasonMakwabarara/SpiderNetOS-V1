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

        try {
            SendEmailJob::dispatchSync([
                'to' => $from,
                'subject' => 'SpiderNetOS SMTP connection test',
                'body' => 'Your email integration is configured correctly.',
                'integration_id' => $integration->id,
            ]);

            return ['ok' => true, 'message' => "Test email sent to {$from}."];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
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
        return [
            'ok' => true,
            'message' => 'Inbound email is handled via the webhook URL on this integration.',
            'imported' => 0,
        ];
    }
}
