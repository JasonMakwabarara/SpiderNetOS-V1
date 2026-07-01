<?php

namespace App\Services;

use App\Models\AiProvider;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Generates text embeddings using a DB-resolved embedding provider. The
 * resulting vector dimension must match config('ai.embedding_dimension') and
 * the `memories.embedding` column.
 */
class EmbeddingService
{
    protected int $timeout;

    public function __construct(protected ProviderResolver $resolver)
    {
        $this->timeout = (int) config('ai.timeout', 30);
    }

    public function embed(string $text): ?array
    {
        $vectors = $this->embedBatch([$text]);

        return $vectors[0] ?? null;
    }

    /**
     * @param  array<int, string>  $texts
     * @return array<int, array<int, float>>  one vector per input (empty on total failure)
     */
    public function embedBatch(array $texts): array
    {
        $tenantId = TenantContext::tenantId();
        $providers = $this->resolver->embedding($tenantId);

        if ($providers->isEmpty()) {
            Log::warning('EmbeddingService: no embedding provider configured', ['tenant' => $tenantId]);

            return [];
        }

        foreach ($providers as $provider) {
            try {
                $vectors = $this->callProvider($provider, $texts);
                if ($vectors !== null) {
                    $this->assertDimension($vectors, $provider);

                    return $vectors;
                }
            } catch (\Throwable $e) {
                Log::warning('EmbeddingService provider failed', [
                    'provider' => $provider->name,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [];
    }

    protected function callProvider(AiProvider $provider, array $texts): ?array
    {
        $base = $provider->effectiveBaseUrl();
        $http = Http::timeout($this->timeout);

        if ($provider->type === AiProvider::TYPE_OLLAMA) {
            $out = [];
            foreach ($texts as $text) {
                $response = $http->post("{$base}/api/embeddings", [
                    'model' => $provider->embedding_model,
                    'prompt' => $text,
                ]);
                if (! $response->successful()) {
                    return null;
                }
                $out[] = $response->json('embedding');
            }

            return $out;
        }

        // OpenAI / Azure / custom OpenAI-compatible.
        $response = $http->withHeaders([
            'Authorization' => 'Bearer '.$provider->api_key,
            'Content-Type' => 'application/json',
        ])->post("{$base}/v1/embeddings", [
            'model' => $provider->embedding_model,
            'input' => $texts,
        ]);

        if (! $response->successful()) {
            return null;
        }

        return array_map(fn ($row) => $row['embedding'], $response->json('data', []));
    }

    protected function assertDimension(array $vectors, AiProvider $provider): void
    {
        $expected = (int) config('ai.embedding_dimension', 1536);
        $actual = isset($vectors[0]) ? count($vectors[0]) : 0;

        if ($actual !== 0 && $actual !== $expected) {
            Log::error('Embedding dimension mismatch', [
                'provider' => $provider->name,
                'expected' => $expected,
                'actual' => $actual,
            ]);
            throw new \RuntimeException(
                "Embedding dimension mismatch: provider '{$provider->name}' returned {$actual}, expected {$expected}. ".
                'Update config(ai.embedding_dimension) and the memories column to match the model.'
            );
        }
    }
}
