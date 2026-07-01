<?php

namespace App\Console\Commands;

use App\Jobs\RunFlowJob;
use App\Models\Flow;
use Cron\CronExpression;
use Illuminate\Console\Command;

/**
 * Dispatches schedule-triggered flows whose cron expression is due. Intended to
 * run every minute via the framework scheduler (see routes/console.php).
 */
class RunScheduledFlows extends Command
{
    protected $signature = 'flows:run-scheduled';

    protected $description = 'Dispatch scheduled flows whose cron expression is due now';

    public function handle(): int
    {
        // Cross-tenant scan; tenant is taken from each flow.
        $flows = Flow::withoutGlobalScopes()
            ->where('trigger', Flow::TRIGGER_SCHEDULE)
            ->where('is_active', true)
            ->get();

        $dispatched = 0;

        foreach ($flows as $flow) {
            $cron = $flow->trigger_config['cron'] ?? null;
            if (! $cron || ! CronExpression::isValidExpression($cron)) {
                continue;
            }

            try {
                if ((new CronExpression($cron))->isDue(now())) {
                    RunFlowJob::dispatchResilient($flow->id, $flow->tenant_id, [
                        'trigger' => 'schedule',
                        'at' => now()->toIso8601String(),
                    ], Flow::TRIGGER_SCHEDULE);
                    $dispatched++;
                }
            } catch (\Throwable $e) {
                $this->warn("Flow #{$flow->id} cron error: {$e->getMessage()}");
            }
        }

        $this->info("Dispatched {$dispatched} scheduled flow(s).");

        return self::SUCCESS;
    }
}
