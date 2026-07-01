<?php

namespace App\Services;

use App\Jobs\RunAgentJob;
use App\Models\Agent;
use App\Models\Record;

/**
 * Fans out record-event triggers to agents configured for autonomous mode.
 * Suppression prevents agents that write records from re-triggering themselves.
 */
class AgentDispatcher
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

        $agents = Agent::query()
            ->where('status', 'active')
            ->get();

        foreach ($agents as $agent) {
            $auto = $agent->config['autonomous'] ?? null;
            if (! is_array($auto) || ! ($auto['enabled'] ?? false)) {
                continue;
            }

            if (($auto['object'] ?? null) !== $objectSlug) {
                continue;
            }

            $events = $auto['events'] ?? ['created'];
            if (! in_array($event, $events, true)) {
                continue;
            }

            $template = $auto['prompt']
                ?? 'A {{object}} record was {{event}}. Review the data and take appropriate action.';

            $task = static::renderTemplate($template, $record, $event, $objectSlug);

            RunAgentJob::dispatchResilient($agent->id, $record->tenant_id, $task, [
                'event' => $event,
                'object' => $objectSlug,
                'record_id' => $record->id,
                'record' => $record->data ?? [],
            ], 'record-event');
        }
    }

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

    protected static function renderTemplate(string $template, Record $record, string $event, string $object): string
    {
        $replacements = [
            '{{event}}' => $event,
            '{{object}}' => $object,
            '{{record_id}}' => (string) $record->id,
            '{{title}}' => $record->title(),
        ];

        foreach ($record->data ?? [] as $key => $value) {
            $replacements["{{record.{$key}}}"] = is_scalar($value) ? (string) $value : json_encode($value);
        }

        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }
}
