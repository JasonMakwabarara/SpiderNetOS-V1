<?php

namespace App\Jobs;

use App\Models\AgentRun;
use App\Services\HannahService;
use App\Support\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunHannahJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public int $runId,
        public ?int $tenantId,
    ) {
    }

    public function handle(HannahService $hannah): void
    {
        TenantContext::withTenant($this->tenantId, function () use ($hannah) {
            $run = AgentRun::find($this->runId);
            if (! $run) {
                return;
            }

            $hannah->orchestrate(
                $run->goal ?? '',
                $run->context ?? [],
                $run->trigger ?? 'orchestrate',
                $run,
            );
        });
    }

    public static function dispatchResilient(int $runId, ?int $tenantId): void
    {
        try {
            static::dispatch($runId, $tenantId);
        } catch (\Throwable $e) {
            static::dispatchSync($runId, $tenantId);
        }
    }
}
