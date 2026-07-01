<?php

namespace App\Services\Integrations;

use App\Models\Integration;
use App\Models\ObjectType;
use App\Services\AgentDispatcher;
use App\Services\FlowDispatcher;
use App\Services\RecordWriter;
use Illuminate\Support\Facades\Http;

class HubSpotConnector implements IntegrationConnector
{
    public function __construct(
        protected RecordWriter $records,
        protected HubSpotOAuth $oauth,
    ) {
    }

    public function test(Integration $integration): array
    {
        try {
            $key = $this->oauth->accessToken($integration);
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }

        try {
            $res = Http::withToken($key)->timeout(10)
                ->get('https://api.hubapi.com/crm/v3/objects/contacts', ['limit' => 1]);

            return $res->successful()
                ? ['ok' => true, 'message' => 'HubSpot connection verified.']
                : ['ok' => false, 'message' => 'HubSpot API returned HTTP '.$res->status()];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function send(Integration $integration, string $action, array $payload): array
    {
        return ['ok' => false, 'message' => 'HubSpot send is not implemented; use sync to import contacts.'];
    }

    public function sync(Integration $integration): array
    {
        try {
            $key = $this->oauth->accessToken($integration);
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage(), 'imported' => 0];
        }

        $objectSlug = $integration->config['target_object'] ?? 'people';
        $object = ObjectType::where('slug', $objectSlug)->first();

        if (! $object) {
            return ['ok' => false, 'message' => "Target object '{$objectSlug}' not found.", 'imported' => 0];
        }

        try {
            $res = Http::withToken($key)->timeout(30)
                ->get('https://api.hubapi.com/crm/v3/objects/contacts', ['limit' => 25, 'properties' => 'firstname,lastname,email,phone']);

            if (! $res->successful()) {
                return ['ok' => false, 'message' => 'HubSpot API error: '.$res->status(), 'imported' => 0];
            }

            $imported = 0;
            $create = function () use ($res, $object, &$imported) {
                foreach ($res->json('results') ?? [] as $contact) {
                    $props = $contact['properties'] ?? [];
                    $name = trim(($props['firstname'] ?? '').' '.($props['lastname'] ?? ''));
                    if ($name === '' && empty($props['email'])) {
                        continue;
                    }
                    $this->records->create($object, array_filter([
                        'name' => $name ?: ($props['email'] ?? 'Contact'),
                        'email' => $props['email'] ?? null,
                        'phone' => $props['phone'] ?? null,
                    ]));
                    $imported++;
                }
            };

            FlowDispatcher::suppress(fn () => AgentDispatcher::suppress($create));

            return ['ok' => true, 'message' => "Imported {$imported} contacts from HubSpot.", 'imported' => $imported];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage(), 'imported' => 0];
        }
    }
}
