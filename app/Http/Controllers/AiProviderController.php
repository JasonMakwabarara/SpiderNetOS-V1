<?php

namespace App\Http\Controllers;

use App\Models\AiProvider;
use App\Services\EventLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Super-admin CRUD for AI providers. Gated by the EnsureSuperAdmin middleware.
 *
 * Platform-scoped providers are managed here. Per-tenant override providers
 * land with the Phase 8 tenant-admin panel.
 */
class AiProviderController extends Controller
{
    public function index()
    {
        // api_key is hidden by the model; never returned.
        return response()->json(AiProvider::orderBy('scope')->orderBy('priority')->get());
    }

    public function store(Request $request)
    {
        $data = $this->validateProvider($request);

        $provider = AiProvider::create($data);
        EventLogger::log('ai_provider.created', (string) $provider->id, ['name' => $provider->name, 'type' => $provider->type]);

        return response()->json($provider, 201);
    }

    public function show(AiProvider $aiProvider)
    {
        return response()->json($aiProvider);
    }

    public function update(Request $request, AiProvider $aiProvider)
    {
        $data = $this->validateProvider($request, $aiProvider);

        // Empty api_key on update means "leave unchanged".
        if (array_key_exists('api_key', $data) && ($data['api_key'] === null || $data['api_key'] === '')) {
            unset($data['api_key']);
        }

        $aiProvider->update($data);
        EventLogger::log('ai_provider.updated', (string) $aiProvider->id, ['name' => $aiProvider->name]);

        return response()->json($aiProvider);
    }

    public function destroy(AiProvider $aiProvider)
    {
        EventLogger::log('ai_provider.deleted', (string) $aiProvider->id, ['name' => $aiProvider->name]);
        $aiProvider->delete();

        return response()->json(null, 204);
    }

    protected function validateProvider(Request $request, ?AiProvider $existing = null): array
    {
        return $request->validate([
            'type' => ['required', Rule::in([
                AiProvider::TYPE_OPENAI,
                AiProvider::TYPE_OLLAMA,
                AiProvider::TYPE_AZURE,
                AiProvider::TYPE_ANTHROPIC,
                AiProvider::TYPE_DEEPSEEK,
                AiProvider::TYPE_CUSTOM,
            ])],
            'name' => ['required', 'string', 'max:255'],
            'base_url' => ['nullable', 'url'],
            'api_key' => [$existing ? 'nullable' : 'nullable', 'string'],
            'chat_model' => ['nullable', 'string'],
            'embedding_model' => ['nullable', 'string'],
            'enabled' => ['boolean'],
            'priority' => ['integer', 'min:0'],
            'scope' => ['required', Rule::in([AiProvider::SCOPE_PLATFORM, AiProvider::SCOPE_TENANT])],
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
            'config' => ['nullable', 'array'],
        ]);
    }
}
