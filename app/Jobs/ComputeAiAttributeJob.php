<?php

namespace App\Jobs;

use App\Models\Attribute;
use App\Models\Record;
use App\Services\ActivityLogger;
use App\Services\EventLogger;
use App\Services\InferenceService;
use App\Support\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Computes the value of every AI attribute on a record by rendering each
 * attribute's prompt template against the record's data and calling the LLM.
 *
 * Runs inside the record's tenant context so ProviderResolver and the tenant
 * global scope resolve correctly off the request. Values are written back with
 * saveQuietly() to avoid re-triggering record-event automations / recursion.
 */
class ComputeAiAttributeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(public int $recordId, public ?int $tenantId)
    {
    }

    public function handle(InferenceService $inference): void
    {
        TenantContext::withTenant($this->tenantId, function () use ($inference) {
            $record = Record::find($this->recordId);
            if (! $record) {
                return;
            }

            $objectType = $record->objectType;
            if (! $objectType) {
                return;
            }

            $aiAttributes = $objectType->attributes()
                ->where('type', Attribute::TYPE_AI)
                ->get();

            if ($aiAttributes->isEmpty()) {
                return;
            }

            $data = $record->data ?? [];
            $changed = false;

            foreach ($aiAttributes as $attribute) {
                $template = $attribute->config['prompt'] ?? null;
                if (! $template) {
                    continue;
                }

                $rendered = $this->render($template, $data);

                $result = $inference->chatMessages([
                    ['role' => 'system', 'content' => 'You compute the value of a single data field. Respond with ONLY the value — no preamble, labels, or quotation marks.'],
                    ['role' => 'user', 'content' => $rendered],
                ], ['temperature' => 0.2, 'max_tokens' => 400]);

                // No provider answered (unconfigured / failed) — leave unset so it
                // can be retried later rather than caching an error string.
                if (($result['provider'] ?? null) === null) {
                    Log::info('ComputeAiAttributeJob: no provider answered; skipping', [
                        'record' => $record->id,
                        'attribute' => $attribute->slug,
                    ]);

                    continue;
                }

                $value = trim((string) ($result['content'] ?? ''));
                if ($value !== '') {
                    $data[$attribute->slug] = $value;
                    $changed = true;
                }
            }

            if ($changed) {
                $record->data = $data;
                $record->saveQuietly();
                EventLogger::log('record.ai_computed', (string) $record->id, ['object' => $objectType->slug]);
                ActivityLogger::system($record, 'AI fields computed', [
                    'fields' => $aiAttributes->pluck('slug')->all(),
                ], '✨');
            }
        });
    }

    /**
     * Replace {{ slug }} placeholders with the record's values. Non-scalar
     * values (arrays) are JSON-encoded.
     */
    protected function render(string $template, array $data): string
    {
        return preg_replace_callback('/\{\{\s*([\w.]+)\s*\}\}/', function ($m) use ($data) {
            $value = $data[$m[1]] ?? '';

            return is_scalar($value) ? (string) $value : json_encode($value);
        }, $template);
    }
}
