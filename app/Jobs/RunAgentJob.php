<?php

namespace App\Jobs;

use App\Models\Agent;
use App\Models\AgentRun;
use App\Models\Record;
use App\Services\ActivityLogger;
use App\Services\AgentDispatcher;
use App\Services\AgentWorker;
use App\Support\TenantBroadcast;
use App\Support\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Runs a single agent task asynchronously inside its tenant context. Used for
 * autonomous record-event triggers and background agent work.
 */
class RunAgentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public int $agentId,
        public ?int $tenantId,
        public string $task,
        public array $context = [],
        public string $trigger = 'autonomous',
    ) {
    }

    public function handle(AgentWorker $worker): void
    {
        TenantContext::withTenant($this->tenantId, function () use ($worker) {
            $agent = Agent::find($this->agentId);
            if (! $agent || $agent->status !== 'active') {
                return;
            }

            $run = AgentRun::create([
                'agent_id' => $agent->id,
                'orchestrator' => AgentRun::ORCHESTRATOR_AUTONOMOUS,
                'status' => AgentRun::STATUS_RUNNING,
                'trigger' => $this->trigger,
                'goal' => $this->task,
                'context' => $this->context,
                'record_id' => $this->context['record_id'] ?? null,
                'steps' => [],
                'started_at' => now(),
            ]);

            try {
                $result = AgentDispatcher::suppress(
                    fn () => $worker->run($agent, $this->task, $this->context)
                );

                $run->update([
                    'status' => AgentRun::STATUS_SUCCESS,
                    'steps' => $result['steps'],
                    'result' => $result['response'],
                    'finished_at' => now(),
                ]);

                $this->logRecordActivity($run, $agent, 'success');
            } catch (\Throwable $e) {
                Log::error('RunAgentJob failed', ['agent' => $agent->id, 'error' => $e->getMessage()]);
                $run->update([
                    'status' => AgentRun::STATUS_FAILED,
                    'error' => $e->getMessage(),
                    'finished_at' => now(),
                ]);
                $this->logRecordActivity($run, $agent, 'failed');
            }

            $tenantId = $this->tenantId ?? $agent->tenant_id;
            TenantBroadcast::agentRun($tenantId, $run->id, $agent->id, $run->status);
        });
    }

    public static function dispatchResilient(
        int $agentId,
        ?int $tenantId,
        string $task,
        array $context = [],
        string $trigger = 'autonomous',
    ): void {
        try {
            static::dispatch($agentId, $tenantId, $task, $context, $trigger);
        } catch (\Throwable $e) {
            static::dispatchSync($agentId, $tenantId, $task, $context, $trigger);
        }
    }

    protected function logRecordActivity(AgentRun $run, Agent $agent, string $status): void
    {
        if (! $run->record_id) {
            return;
        }

        $record = Record::find($run->record_id);
        if (! $record) {
            return;
        }

        $icon = $status === 'success' ? '🤖' : '⚠️';
        $label = $status === 'success'
            ? "{$agent->name} ran autonomously"
            : "{$agent->name} autonomous run failed";

        ActivityLogger::system($record, $label, [
            'run_id' => $run->id,
            'agent_id' => $agent->id,
        ], $icon);
    }
}
