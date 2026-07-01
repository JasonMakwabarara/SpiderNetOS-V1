<?php

namespace App\Services;

use App\Jobs\RunFlowJob;
use App\Models\Activity;
use App\Models\Flow;
use App\Models\Integration;
use App\Models\Record;

/**
 * Fans out record-event triggers to matching flows. Execution is suppressed
 * while a flow is already running (same process) so a flow that creates records
 * cannot re-trigger itself into a loop.
 */
class FlowDispatcher
{
    protected static bool $enabled = true;

    public static function onRecordEvent(Record $record, string $event): void
    {
        if (! static::$enabled) {
            return;
        }

        $objectSlug = $record->objectType?->slug;
        if (! $objectSlug) {
            return;
        }

        $flows = Flow::query()
            ->where('trigger', Flow::TRIGGER_RECORD_EVENT)
            ->where('is_active', true)
            ->get();

        foreach ($flows as $flow) {
            $config = $flow->trigger_config ?? [];

            if (($config['object'] ?? null) !== $objectSlug) {
                continue;
            }

            $events = $config['events'] ?? ['created'];
            if (! in_array($event, $events, true)) {
                continue;
            }

            RunFlowJob::dispatchResilient($flow->id, $record->tenant_id, [
                'event' => $event,
                'object' => $objectSlug,
                'record_id' => $record->id,
                'record' => $record->data ?? [],
            ], Flow::TRIGGER_RECORD_EVENT);
        }
    }

    /**
     * Fan out inbound-email triggers for flows bound to an integration.
     *
     * @param  array{from:string,to:string,subject:string,body:string,message_id:?string}  $email
     */
    public static function onInboundEmail(Integration $integration, array $email, ?Record $record, Activity $activity): void
    {
        if (! static::$enabled) {
            return;
        }

        $flows = Flow::query()
            ->where('trigger', Flow::TRIGGER_INBOUND_EMAIL)
            ->where('is_active', true)
            ->get();

        foreach ($flows as $flow) {
            $config = $flow->trigger_config ?? [];
            $integrationId = $config['integration_id'] ?? null;

            if ($integrationId && (int) $integrationId !== (int) $integration->id) {
                continue;
            }

            RunFlowJob::dispatchResilient($flow->id, $integration->tenant_id, [
                'trigger' => 'inbound-email',
                'email' => $email,
                'record_id' => $record?->id,
                'activity_id' => $activity->id,
            ], Flow::TRIGGER_INBOUND_EMAIL);
        }
    }

    /**
     * Run a callback with record-event dispatching suppressed.
     */
    public static function suppress(callable $callback): mixed
    {
        $previous = static::$enabled;
        static::$enabled = false;

        try {
            return $callback();
        } finally {
            static::$enabled = $previous;
        }
    }
}
