<?php

namespace App\Jobs;

use App\Models\Flow;
use App\Services\FlowDispatcher;
use App\Services\FlowRunner;
use App\Support\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Executes a flow asynchronously inside its tenant context. Dispatched by manual
 * runs, the webhook endpoint, record-event hooks, and the scheduler.
 */
class RunFlowJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public int $flowId,
        public ?int $tenantId,
        public array $context = [],
        public string $trigger = 'manual',
    ) {
    }

    public function handle(FlowRunner $runner): void
    {
        TenantContext::withTenant($this->tenantId, function () use ($runner) {
            $flow = Flow::find($this->flowId);
            if (! $flow || ! $flow->is_active) {
                return;
            }

            // Suppress record-event re-triggering for writes made inside the flow.
            FlowDispatcher::suppress(fn () => $runner->run($flow, $this->context, $this->trigger));
        });
    }

    /**
     * Dispatch onto the queue, falling back to synchronous execution when the
     * queue backend is unavailable (e.g. local dev without Redis/worker).
     */
    public static function dispatchResilient(int $flowId, ?int $tenantId, array $context = [], string $trigger = 'manual'): void
    {
        try {
            static::dispatch($flowId, $tenantId, $context, $trigger);
        } catch (\Throwable $e) {
            static::dispatchSync($flowId, $tenantId, $context, $trigger);
        }
    }
}
