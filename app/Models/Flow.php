<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An automation workflow. `trigger` is the trigger TYPE (manual|webhook|
 * record-event|schedule) and `trigger_config` holds its settings. Execution
 * follows the `graph` DAG when present, else the linear `config.actions`.
 */
class Flow extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'name', 'description', 'slug', 'trigger', 'trigger_config',
        'webhook_token', 'config', 'graph', 'executions', 'is_active',
    ];

    protected $casts = [
        'config' => 'array',
        'trigger_config' => 'array',
        'graph' => 'array',
        'is_active' => 'boolean',
    ];

    protected $appends = ['webhook_url'];

    public function getWebhookUrlAttribute(): ?string
    {
        return $this->webhook_token
            ? rtrim(config('app.url'), '/').'/api/hooks/flow/'.$this->webhook_token
            : null;
    }

    public const TRIGGER_MANUAL = 'manual';
    public const TRIGGER_WEBHOOK = 'webhook';
    public const TRIGGER_RECORD_EVENT = 'record-event';
    public const TRIGGER_SCHEDULE = 'schedule';
    public const TRIGGER_INBOUND_EMAIL = 'inbound-email';

    public function runs(): HasMany
    {
        return $this->hasMany(FlowRun::class);
    }

    /** Graph nodes, or empty array. */
    public function nodes(): array
    {
        return $this->graph['nodes'] ?? [];
    }

    /** Graph edges, or empty array. */
    public function edges(): array
    {
        return $this->graph['edges'] ?? [];
    }

    public function hasGraph(): bool
    {
        return ! empty($this->nodes());
    }
}
