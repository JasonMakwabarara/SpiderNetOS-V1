<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single execution of a {@see Flow}. Records the trigger, the input context,
 * a per-node step log, and timing/outcome — the basis for execution history and
 * the monitoring dashboard.
 */
class FlowRun extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'flow_id', 'status', 'trigger',
        'context', 'steps', 'error', 'started_at', 'finished_at',
    ];

    protected $casts = [
        'context' => 'array',
        'steps' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_RUNNING = 'running';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';

    public function flow(): BelongsTo
    {
        return $this->belongsTo(Flow::class);
    }

    public function durationMs(): ?int
    {
        if (! $this->started_at || ! $this->finished_at) {
            return null;
        }

        return (int) $this->started_at->diffInMilliseconds($this->finished_at);
    }
}
