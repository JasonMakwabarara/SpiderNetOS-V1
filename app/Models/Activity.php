<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A timeline entry. User-authored entries (notes, calls, emails, meetings,
 * tasks) and auto-captured system entries (record changes, flow runs, AI
 * computation) share this table to form a single per-record activity feed.
 */
class Activity extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'record_id', 'object_type_id', 'type', 'title',
        'body', 'icon', 'meta', 'is_system', 'user_id', 'occurred_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'is_system' => 'boolean',
        'occurred_at' => 'datetime',
    ];

    protected $appends = ['author_name'];

    /** User-authorable activity types. */
    public const USER_TYPES = ['note', 'call', 'email', 'meeting', 'task'];

    public function record(): BelongsTo
    {
        return $this->belongsTo(Record::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getAuthorNameAttribute(): string
    {
        if ($this->is_system) {
            return 'System';
        }

        return $this->relationLoaded('user')
            ? ($this->user?->name ?? 'Unknown')
            : (User::find($this->user_id)?->name ?? 'Unknown');
    }
}
