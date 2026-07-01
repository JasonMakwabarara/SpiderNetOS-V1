<?php

namespace App\Services\Integrations;

use App\Models\Integration;
use App\Models\ObjectType;
use App\Models\Record;
use App\Services\AgentDispatcher;
use App\Services\FlowDispatcher;
use App\Services\RecordWriter;
use Illuminate\Support\Facades\Http;

class SalesforceConnector implements IntegrationConnector
{
    public function __construct(protected RecordWriter $records)
    {
    }

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
        $url = rtrim($integration->credentials['instance_url'] ?? '', '/');
        $token = $integration->credentials['access_token'] ?? '';
        if ($url === '' || $token === '') {
            return ['ok' => false, 'message' => 'Salesforce is not configured.'];
        }

        $object = $integration->config['salesforce_object'] ?? 'Contact';
        $fields = array_filter([
            'LastName' => $payload['lastname'] ?? $payload['name'] ?? 'Contact',
            'FirstName' => $payload['firstname'] ?? null,
            'Email' => $payload['email'] ?? $payload['to'] ?? null,
            'Phone' => $payload['phone'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        try {
            $res = Http::withToken($token)->timeout(15)
                ->post("{$url}/services/data/v59.0/sobjects/{$object}", $fields);

            if (! $res->successful()) {
                return ['ok' => false, 'message' => 'Salesforce API error: '.$res->status().' '.$res->body()];
            }

            return [
                'ok' => true,
                'message' => 'Salesforce record created.',
                'salesforce_id' => $res->json('id'),
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function sync(Integration $integration): array
    {
        $url = rtrim($integration->credentials['instance_url'] ?? '', '/');
        $token = $integration->credentials['access_token'] ?? '';
        if ($url === '' || $token === '') {
            return ['ok' => false, 'message' => 'Salesforce is not configured.', 'imported' => 0, 'updated' => 0];
        }

        $soql = $integration->config['soql']
            ?? 'SELECT Id, Name, Email, Phone FROM Contact LIMIT 200';

        $objectSlug = $integration->config['target_object'] ?? 'people';
        $object = ObjectType::where('slug', $objectSlug)->first();
        if (! $object) {
            return ['ok' => false, 'message' => "Target object '{$objectSlug}' not found.", 'imported' => 0, 'updated' => 0];
        }

        $imported = 0;
        $updated = 0;

        try {
            $res = Http::withToken($token)->timeout(30)
                ->get("{$url}/services/data/v59.0/query", ['q' => $soql]);

            if (! $res->successful()) {
                return ['ok' => false, 'message' => 'Salesforce query failed: '.$res->status(), 'imported' => 0, 'updated' => 0];
            }

            $syncBatch = function () use ($res, $object, &$imported, &$updated) {
                foreach ($res->json('records') ?? [] as $row) {
                    $sfId = (string) ($row['Id'] ?? '');
                    if ($sfId === '') {
                        continue;
                    }

                    $data = array_filter([
                        'name' => $row['Name'] ?? 'Contact',
                        'email' => $row['Email'] ?? null,
                        'phone' => $row['Phone'] ?? null,
                        'salesforce_id' => $sfId,
                    ], fn ($v) => $v !== null && $v !== '');

                    $existing = Record::where('object_type_id', $object->id)
                        ->where('data->salesforce_id', $sfId)
                        ->first();

                    if (! $existing && ! empty($row['Email'])) {
                        $existing = Record::where('object_type_id', $object->id)
                            ->where('data->email', $row['Email'])
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

            return [
                'ok' => true,
                'message' => "Salesforce sync complete — {$imported} created, {$updated} updated.",
                'imported' => $imported,
                'updated' => $updated,
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage(), 'imported' => $imported, 'updated' => $updated];
        }
    }
}
