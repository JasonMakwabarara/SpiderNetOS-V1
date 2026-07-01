<?php

namespace App\Services\Integrations;

use App\Models\Integration;
use App\Models\ObjectType;
use App\Models\Record;
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
        try {
            $key = $this->oauth->accessToken($integration);
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }

        $properties = array_filter([
            'email' => $payload['email'] ?? $payload['to'] ?? null,
            'firstname' => $payload['firstname'] ?? null,
            'lastname' => $payload['lastname'] ?? null,
            'phone' => $payload['phone'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        if ($properties === []) {
            return ['ok' => false, 'message' => 'At least email or name is required to create a HubSpot contact.'];
        }

        try {
            $hubspotId = $payload['hubspot_id'] ?? null;
            if ($action === 'update' && $hubspotId) {
                $res = Http::withToken($key)->timeout(15)
                    ->patch("https://api.hubapi.com/crm/v3/objects/contacts/{$hubspotId}", [
                        'properties' => $properties,
                    ]);
            } else {
                $res = Http::withToken($key)->timeout(15)
                    ->post('https://api.hubapi.com/crm/v3/objects/contacts', [
                        'properties' => $properties,
                    ]);
            }

            if (! $res->successful()) {
                return ['ok' => false, 'message' => 'HubSpot API error: '.$res->status().' '.$res->body()];
            }

            $id = $res->json('id');

            return [
                'ok' => true,
                'message' => $action === 'update' ? 'HubSpot contact updated.' : 'HubSpot contact created.',
                'hubspot_id' => $id,
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function sync(Integration $integration): array
    {
        try {
            $key = $this->oauth->accessToken($integration);
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage(), 'imported' => 0, 'updated' => 0];
        }

        $objectSlug = $integration->config['target_object'] ?? 'people';
        $object = ObjectType::where('slug', $objectSlug)->first();

        if (! $object) {
            return ['ok' => false, 'message' => "Target object '{$objectSlug}' not found.", 'imported' => 0, 'updated' => 0];
        }

        $imported = 0;
        $updated = 0;
        $after = null;

        try {
            do {
                $params = [
                    'limit' => 100,
                    'properties' => 'firstname,lastname,email,phone',
                ];
                if ($after) {
                    $params['after'] = $after;
                }

                $res = Http::withToken($key)->timeout(30)
                    ->get('https://api.hubapi.com/crm/v3/objects/contacts', $params);

                if (! $res->successful()) {
                    return ['ok' => false, 'message' => 'HubSpot API error: '.$res->status(), 'imported' => $imported, 'updated' => $updated];
                }

                $syncBatch = function () use ($res, $object, &$imported, &$updated) {
                    foreach ($res->json('results') ?? [] as $contact) {
                        $hubspotId = (string) ($contact['id'] ?? '');
                        $props = $contact['properties'] ?? [];
                        $name = trim(($props['firstname'] ?? '').' '.($props['lastname'] ?? ''));
                        if ($name === '' && empty($props['email'])) {
                            continue;
                        }

                        $data = array_filter([
                            'name' => $name ?: ($props['email'] ?? 'Contact'),
                            'email' => $props['email'] ?? null,
                            'phone' => $props['phone'] ?? null,
                            'hubspot_id' => $hubspotId,
                        ], fn ($v) => $v !== null && $v !== '');

                        $existing = Record::where('object_type_id', $object->id)
                            ->where('data->hubspot_id', $hubspotId)
                            ->first();

                        if (! $existing && ! empty($props['email'])) {
                            $existing = Record::where('object_type_id', $object->id)
                                ->where('data->email', $props['email'])
                                ->first();
                        }

                        if ($existing) {
                            $this->records->update($existing, $data);
                            $updated++;
                        } else {
                            $this->records->create($object, $data);
                            $imported++;
                        }
                    }
                };

                FlowDispatcher::suppress(fn () => AgentDispatcher::suppress($syncBatch));

                $after = $res->json('paging.next.after');
            } while ($after);

            return [
                'ok' => true,
                'message' => "HubSpot sync complete — {$imported} created, {$updated} updated.",
                'imported' => $imported,
                'updated' => $updated,
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage(), 'imported' => $imported, 'updated' => $updated];
        }
    }
}
