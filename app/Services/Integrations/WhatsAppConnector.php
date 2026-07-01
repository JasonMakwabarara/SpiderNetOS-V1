<?php

namespace App\Services\Integrations;

use App\Models\Integration;
use Illuminate\Support\Facades\Http;

class WhatsAppConnector implements IntegrationConnector
{
    public function test(Integration $integration): array
    {
        $token = $integration->credentials['access_token'] ?? '';
        $phoneId = $integration->credentials['phone_number_id'] ?? '';
        if ($token === '' || $phoneId === '') {
            return ['ok' => false, 'message' => 'phone_number_id and access_token are required.'];
        }

        return ['ok' => true, 'message' => 'WhatsApp Cloud API configured.'];
    }

    public function send(Integration $integration, string $action, array $payload): array
    {
        $token = $integration->credentials['access_token'] ?? '';
        $phoneId = $integration->credentials['phone_number_id'] ?? '';
        $to = $payload['to'] ?? '';
        $body = $payload['message'] ?? $payload['body'] ?? '';

        if ($token === '' || $phoneId === '' || $to === '' || $body === '') {
            return ['ok' => false, 'message' => 'to, message, and credentials are required.'];
        }

        try {
            $url = "https://graph.facebook.com/v19.0/{$phoneId}/messages";
            $res = Http::withToken($token)->timeout(15)->post($url, [
                'messaging_product' => 'whatsapp',
                'to' => preg_replace('/\D/', '', $to),
                'type' => 'text',
                'text' => ['body' => $body],
            ]);

            return $res->successful()
                ? ['ok' => true, 'message' => 'WhatsApp message sent.', 'data' => $res->json()]
                : ['ok' => false, 'message' => 'WhatsApp API error: '.$res->body()];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function sync(Integration $integration): array
    {
        return ['ok' => true, 'message' => 'WhatsApp sync not supported.', 'imported' => 0];
    }
}
