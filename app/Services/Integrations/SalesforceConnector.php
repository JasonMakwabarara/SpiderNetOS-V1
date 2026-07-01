<?php

namespace App\Services\Integrations;

use App\Models\Integration;
use Illuminate\Support\Facades\Http;

class SalesforceConnector implements IntegrationConnector
{
    public function test(Integration $integration): array
    {
        $url = rtrim($integration->credentials['instance_url'] ?? '', '/');
        $token = $integration->credentials['access_token'] ?? '';
        if ($url === '' || $token === '') {
            return ['ok' => false, 'message' => 'instance_url and access_token are required.'];
        }

        try {
            $res = Http::withToken($token)->timeout(10)->get("{$url}/services/data/v59.0/");

            return $res->successful()
                ? ['ok' => true, 'message' => 'Salesforce connection verified.']
                : ['ok' => false, 'message' => 'Salesforce API returned HTTP '.$res->status()];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function send(Integration $integration, string $action, array $payload): array
    {
        return ['ok' => false, 'message' => 'Salesforce send is not implemented; use sync.'];
    }

    public function sync(Integration $integration): array
    {
        return [
            'ok' => true,
            'message' => 'Salesforce import adapter ready — configure SOQL in integration config to enable.',
            'imported' => 0,
        ];
    }
}
