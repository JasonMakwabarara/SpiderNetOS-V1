<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-user, per-tenant long-term memory: a rolling summary plus discrete facts
 * the assistant has learned. Injected into system prompts for personalisation.
 */
class MemoryProfile extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'user_id', 'summary', 'facts'];

    protected $casts = ['facts' => 'array'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
