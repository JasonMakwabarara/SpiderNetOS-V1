<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\AiProvider;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Unified AI chat service.
 *
 * Providers are resolved from the database via ProviderResolver (no env keys,
 * no hardcoded provider literals). The resolved providers are tried in order as
 * a fallback chain. Retries with exponential backoff and a per tenant+provider
 * daily cost cap are preserved. TLS verification is always ON.
 */
class InferenceService
{
    protected int $maxRetries;
    protected int $retryDelay;
    protected int $timeout;
    protected float $costCap;

    public function __construct(protected ProviderResolver $resolver)
    {
        $this->maxRetries = (int) config('ai.max_retries', 3);
        $this->retryDelay = (int) config('ai.retry_delay', 1);
        $this->timeout = (int) config('ai.timeout', 30);
        $this->costCap = (float) config('ai.daily_cost_cap', 0.50);
    }

    /**
     * Send a single user message (optionally role-flavoured by an agent).
     */
    public function chat(string $message, ?Agent $agent = null): string
    {
        $messages = [];
        if ($systemPrompt = $this->getSystemPrompt($agent)) {
            $messages[] = ['role' => 'system', 'content' => $systemPrompt];
        }
        $messages[] = ['role' => 'user', 'content' => $message];

        $result = $this->chatMessages($messages);

        return $result['content'] ?? 'AI service temporarily unavailable. Please try again later.';
    }

    /**
     * Send a full messages array. Returns the raw decoded provider response so
     * callers (e.g. Atlas tool-calling) can inspect tool calls.
     *
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<string, mixed>  $options  e.g. ['tools' => [...], 'temperature' => 0.2]
     * @return array{content: ?string, raw: ?array, provider: ?string}
     */
    public function chatMessages(array $messages, array $options = []): array
    {
        $tenantId = TenantContext::tenantId();
        $providers = $this->resolver->chat($tenantId);

        if ($providers->isEmpty()) {
            Log::warning('InferenceService: no chat provider configured for tenant', ['tenant' => $tenantId]);

            return ['content' => 'No AI provider is configured. Ask an administrator to add one.', 'raw' => null, 'provider' => null];
        }

        foreach ($providers as $provider) {
            if (! $this->withinCostCap($tenantId, $provider)) {
                Log::warning('InferenceService: daily cost cap reached, skipping provider', [
                    'tenant' => $tenantId,
                    'provider' => $provider->id,
                ]);

                continue;
            }

            $result = $this->callProviderWithRetry($provider, $messages, $options);

            if ($result !== null) {
                $this->trackCost($tenantId, $provider, $this->estimateCost($provider, $messages, $result));

                return [
                    'content' => $this->extractContent($provider, $result),
                    'raw' => $result,
                    'provider' => $provider->name,
                ];
            }
        }

        return ['content' => 'AI service temporarily unavailable. Please try again later.', 'raw' => null, 'provider' => null];
    }

    protected function callProviderWithRetry(AiProvider $provider, array $messages, array $options): ?array
    {
        for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
            try {
                $response = $this->dispatch($provider, $messages, $options);

                if ($response->successful()) {
                    Log::info('AI provider call succeeded', ['provider' => $provider->name, 'attempt' => $attempt]);

                    return $response->json();
                }

                Log::warning('AI provider error', [
                    'provider' => $provider->name,
                    'attempt' => $attempt,
                    'status' => $response->status(),
                ]);
            } catch (\Throwable $e) {
                Log::warning('AI provider call failed', [
                    'provider' => $provider->name,
                    'attempt' => $attempt,
                    'error' => $e->getMessage(),
                ]);
            }

            if ($attempt < $this->maxRetries) {
                sleep($this->retryDelay * (2 ** ($attempt - 1)));
            }
        }

        return null;
    }

    /**
     * Dispatch to the right wire format for the provider type. TLS verification
     * is always enabled (Http client verifies by default).
     */
    protected function dispatch(AiProvider $provider, array $messages, array $options)
    {
        $base = $provider->effectiveBaseUrl();
        $http = Http::timeout($this->timeout);

        if ($provider->type === AiProvider::TYPE_OLLAMA) {
            // Ollama chat API.
            return $http->post("{$base}/api/chat", array_merge([
                'model' => $provider->chat_model,
                'messages' => $messages,
                'stream' => false,
            ], $this->ollamaOptions($options)));
        }

        if ($provider->type === AiProvider::TYPE_ANTHROPIC) {
            return $http->withHeaders([
                'x-api-key' => $provider->api_key,
                'anthropic-version' => '2023-06-01',
                'Content-Type' => 'application/json',
            ])->post("{$base}/v1/messages", array_filter([
                'model' => $provider->chat_model,
                'max_tokens' => $options['max_tokens'] ?? 1024,
                'messages' => $this->toAnthropicMessages($messages),
                'system' => $this->extractSystem($messages),
            ]));
        }

        // OpenAI / Azure OpenAI / custom OpenAI-compatible.
        $payload = array_filter([
            'model' => $provider->chat_model,
            'messages' => $messages,
            'max_tokens' => $options['max_tokens'] ?? 512,
            'temperature' => $options['temperature'] ?? 0.7,
            'tools' => $options['tools'] ?? null,
            'tool_choice' => $options['tool_choice'] ?? null,
        ], fn ($v) => $v !== null);

        return $http->withHeaders([
            'Authorization' => 'Bearer '.$provider->api_key,
            'Content-Type' => 'application/json',
        ])->post($this->openAiChatUrl($base), $payload);
    }

    /**
     * Build the chat-completions URL. If the base URL already includes an API
     * version segment (e.g. ".../v1" or ByteDance ModelArk's ".../api/v3"), only
     * "/chat/completions" is appended; otherwise the standard "/v1" is inserted.
     */
    protected function openAiChatUrl(string $base): string
    {
        if (preg_match('#/(v\d+|api/v\d+)$#', $base)) {
            return "{$base}/chat/completions";
        }

        return "{$base}/v1/chat/completions";
    }

    protected function extractContent(AiProvider $provider, array $result): ?string
    {
        return match ($provider->type) {
            AiProvider::TYPE_OLLAMA => $result['message']['content'] ?? null,
            AiProvider::TYPE_ANTHROPIC => $result['content'][0]['text'] ?? null,
            default => $result['choices'][0]['message']['content'] ?? null,
        };
    }

    protected function toAnthropicMessages(array $messages): array
    {
        return array_values(array_filter(
            $messages,
            fn ($m) => ($m['role'] ?? '') !== 'system'
        ));
    }

    protected function extractSystem(array $messages): ?string
    {
        foreach ($messages as $m) {
            if (($m['role'] ?? '') === 'system') {
                return $m['content'] ?? null;
            }
        }

        return null;
    }

    protected function ollamaOptions(array $options): array
    {
        $out = [];
        if (isset($options['temperature'])) {
            $out['options'] = ['temperature' => $options['temperature']];
        }

        return $out;
    }

    // ---- Cost tracking (per tenant + provider, per day) -------------------

    protected function costKey(?int $tenantId, AiProvider $provider): string
    {
        return sprintf('ai_cost:%s:%s:%s', $tenantId ?? 'platform', $provider->id, date('Y-m-d'));
    }

    protected function withinCostCap(?int $tenantId, AiProvider $provider): bool
    {
        $spent = (float) Cache::get($this->costKey($tenantId, $provider), 0);

        return $spent < $this->costCap;
    }

    protected function trackCost(?int $tenantId, AiProvider $provider, float $cost): void
    {
        $key = $this->costKey($tenantId, $provider);
        $total = (float) Cache::get($key, 0) + $cost;
        Cache::put($key, $total, now()->endOfDay());

        $rollup = sprintf('ai_cost:%s:total:%s', $tenantId ?? 'platform', date('Y-m-d'));
        Cache::put($rollup, (float) Cache::get($rollup, 0) + $cost, now()->endOfDay());

        Log::info('AI cost tracked', [
            'tenant' => $tenantId,
            'provider' => $provider->name,
            'cost' => $cost,
            'total' => $total,
        ]);
    }

    protected function estimateCost(AiProvider $provider, array $messages, array $result): float
    {
        if ($provider->type === AiProvider::TYPE_OLLAMA) {
            return 0.0; // local model
        }

        // Prefer real usage when the provider returns it.
        $tokens = $result['usage']['total_tokens']
            ?? (int) ceil(strlen(json_encode($messages)) / 4);

        // Rough blended estimate; refine per provider/model as needed.
        return $tokens * 0.000000375;
    }

    protected function getSystemPrompt(?Agent $agent): ?string
    {
        if (! $agent) {
            return 'You are a helpful AI assistant. Be concise.';
        }

        $prompts = [
            'Sales' => 'You are a Sales Assistant. Help with product inquiries, pricing, and closing deals. Be professional and persuasive.',
            'Support' => 'You are a Customer Support Agent. Help resolve issues, answer FAQs, and provide troubleshooting steps. Be patient and helpful.',
            'Data Analyst' => 'You are a Data Analyst. Help interpret data, create visualizations, and provide insights. Be analytical and precise.',
            'Developer' => 'You are a Developer Assistant. Help with code, debugging, and best practices. Be technical and accurate.',
            'HR' => 'You are an HR Assistant. Help with employee questions, policies, and onboarding. Be friendly and informative.',
        ];

        return $prompts[$agent->role] ?? "You are {$agent->name}. {$agent->description}";
    }
}
