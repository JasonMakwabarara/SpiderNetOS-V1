<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentRun extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'agent_id', 'record_id', 'orchestrator', 'status', 'trigger',
        'goal', 'context', 'steps', 'result', 'error', 'started_at', 'finished_at',
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

    public const ORCHESTRATOR_HANNAH = 'hannah';
    public const ORCHESTRATOR_AGENT = 'agent';
    public const ORCHESTRATOR_AUTONOMOUS = 'autonomous';

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function record(): BelongsTo
    {
        return $this->belongsTo(Record::class);
    }
}
