<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An AI provider configuration row.
 *
 * Resolution is handled explicitly by ProviderResolver (platform defaults +
 * per-tenant overrides), so this model intentionally does NOT use the tenant
 * global scope — platform-scoped rows must be visible across tenants.
 *
 * @property string $type  openai|ollama|azure_openai|anthropic|deepseek|custom_openai_compatible
 * @property string $scope platform|tenant
 */
class AiProvider extends Model
{
    protected $fillable = [
        'type',
        'name',
        'base_url',
        'api_key',
        'chat_model',
        'embedding_model',
        'enabled',
        'priority',
        'scope',
        'tenant_id',
        'config',
    ];

    protected $casts = [
        'api_key' => 'encrypted',
        'enabled' => 'boolean',
        'priority' => 'integer',
        'config' => 'array',
    ];

    // Never expose the (decrypted) key in API responses.
    protected $hidden = ['api_key'];

    public const TYPE_OPENAI = 'openai';
    public const TYPE_OLLAMA = 'ollama';
    public const TYPE_AZURE = 'azure_openai';
    public const TYPE_ANTHROPIC = 'anthropic';
    public const TYPE_DEEPSEEK = 'deepseek';
    public const TYPE_CUSTOM = 'custom_openai_compatible';

    public const SCOPE_PLATFORM = 'platform';
    public const SCOPE_TENANT = 'tenant';

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function requiresKey(): bool
    {
        // Ollama and custom local servers may not need an API key.
        return ! in_array($this->type, [self::TYPE_OLLAMA], true);
    }

    public function effectiveBaseUrl(): string
    {
        if ($this->base_url) {
            return rtrim($this->base_url, '/');
        }

        return match ($this->type) {
            self::TYPE_OLLAMA => rtrim(config('ai.ollama_endpoint', env('OLLAMA_ENDPOINT', 'http://localhost:11434')), '/'),
            self::TYPE_ANTHROPIC => 'https://api.anthropic.com',
            // DeepSeek's API is OpenAI-compatible. The official endpoint is used
            // by default; point base_url at a BytePlus/Volcengine Ark endpoint to
            // call DeepSeek models via the ByteDance international API instead.
            self::TYPE_DEEPSEEK => 'https://api.deepseek.com',
            default => 'https://api.openai.com',
        };
    }
}
