<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Record;
use Illuminate\Support\Facades\Auth;

/**
 * Writes timeline {@see Activity} entries. Distinct from {@see EventLogger}
 * (low-level audit log): activities are the human-facing, per-record feed.
 *
 * tenant_id is auto-filled by the BelongsToTenant trait from the active
 * TenantContext, so this works both in requests and inside tenant-scoped jobs.
 */
class ActivityLogger
{
    /**
     * Generic writer.
     *
     * @param  array{body?:string,icon?:string,meta?:array,is_system?:bool,user_id?:int,occurred_at?:mixed}  $opts
     */
    public static function log(?Record $record, string $type, string $title, array $opts = []): Activity
    {
        return Activity::create([
            'record_id' => $record?->id,
            'object_type_id' => $record?->object_type_id,
            'type' => $type,
            'title' => $title,
            'body' => $opts['body'] ?? null,
            'icon' => $opts['icon'] ?? null,
            'meta' => $opts['meta'] ?? null,
            'is_system' => $opts['is_system'] ?? false,
            'user_id' => $opts['user_id'] ?? Auth::id(),
            'occurred_at' => $opts['occurred_at'] ?? now(),
        ]);
    }

    /** Auto-captured system entry. */
    public static function system(?Record $record, string $title, array $meta = [], string $icon = '⚙️'): Activity
    {
        return static::log($record, 'system', $title, [
            'meta' => $meta ?: null,
            'icon' => $icon,
            'is_system' => true,
            'user_id' => null,
        ]);
    }
}
