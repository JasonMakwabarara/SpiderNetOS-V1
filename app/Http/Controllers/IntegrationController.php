<?php

namespace App\Http\Controllers;

use App\Models\Integration;
use App\Services\EventLogger;
use App\Services\IntegrationHub;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class IntegrationController extends Controller
{
    public function __construct(protected IntegrationHub $hub)
    {
    }

    public function index()
    {
        return response()->json(Integration::orderBy('name')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::in(Integration::types())],
            'credentials' => ['nullable', 'array'],
            'config' => ['nullable', 'array'],
        ]);

        $integration = Integration::create([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']).'-'.Str::random(5),
            'type' => $validated['type'],
            'status' => Integration::STATUS_DISCONNECTED,
            'credentials' => $validated['credentials'] ?? [],
            'config' => $validated['config'] ?? [],
        ]);

        EventLogger::log('integration.created', (string) $integration->id, [
            'name' => $integration->name,
            'type' => $integration->type,
        ]);

        return response()->json($integration, 201);
    }

    public function update(Request $request, Integration $integration)
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'credentials' => ['nullable', 'array'],
            'config' => ['nullable', 'array'],
        ]);

        if (isset($validated['credentials'])) {
            $validated['credentials'] = array_merge(
                $integration->credentials ?? [],
                $validated['credentials']
            );
        }

        $integration->update($validated);
        EventLogger::log('integration.updated', (string) $integration->id, ['name' => $integration->name]);

        return response()->json($integration->fresh());
    }

    public function destroy(Integration $integration)
    {
        EventLogger::log('integration.deleted', (string) $integration->id, ['name' => $integration->name]);
        $integration->delete();

        return response()->json(null, 204);
    }

    public function test(Integration $integration)
    {
        $result = $this->hub->test($integration);
        EventLogger::log('integration.tested', (string) $integration->id, $result);

        return response()->json($result);
    }

    public function sync(Integration $integration)
    {
        $result = $this->hub->sync($integration);
        EventLogger::log('integration.synced', (string) $integration->id, $result);

        return response()->json($result);
    }

    public function send(Request $request, Integration $integration)
    {
        $payload = $request->validate([
            'action' => ['nullable', 'string'],
            'to' => ['nullable', 'string'],
            'subject' => ['nullable', 'string'],
            'message' => ['nullable', 'string'],
            'body' => ['nullable', 'string'],
        ]);

        $result = $this->hub->send($integration, $payload['action'] ?? 'send', $payload);
        EventLogger::log('integration.sent', (string) $integration->id, $result);

        return response()->json($result, $result['ok'] ? 200 : 422);
    }
}
