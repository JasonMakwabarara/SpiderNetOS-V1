<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A recorded user/assistant (or user/agent) exchange. Feeds the Advanced Memory
 * System: interactions are summarised and embedded for long-term recall.
 */
class Interaction extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'user_id', 'agent_id', 'source', 'prompt', 'response', 'metadata'];

    protected $casts = ['metadata' => 'array'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }
}
