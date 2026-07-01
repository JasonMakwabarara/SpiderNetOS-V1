<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\AgentRun;
use App\Models\FlowRun;
use App\Models\Integration;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

/**
 * Aggregates operational metrics for the monitoring dashboard: system health,
 * agent/flow performance, integration status, and recent activity.
 */
class MonitoringService
{
    public function snapshot(): array
    {
        return [
            'system' => $this->systemHealth(),
            'agents' => $this->agentMetrics(),
            'flows' => $this->flowMetrics(),
            'integrations' => $this->integrationMetrics(),
            'activity' => $this->activityMetrics(),
            'ai' => $this->aiMetrics(),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    protected function systemHealth(): array
    {
        $db = 'error';
        try {
            DB::connection()->getPdo();
            $db = 'ok';
        } catch (\Throwable $e) {
            // degraded
        }

        $queueDriver = config('queue.default', 'sync');
        $queueDepth = null;
        if ($queueDriver !== 'sync') {
            try {
                $queueDepth = Queue::size();
            } catch (\Throwable $e) {
                $queueDepth = null;
            }
        }

        return [
            'status' => $db === 'ok' ? 'healthy' : 'degraded',
            'database' => $db,
            'queue_driver' => $queueDriver,
            'queue_depth' => $queueDepth,
            'cache_driver' => config('cache.default'),
        ];
    }

    protected function agentMetrics(): array
    {
        if (! Schema::hasTable('agent_runs')) {
            return ['total' => 0, 'by_status' => [], 'recent_failures' => 0];
        }

        $byStatus = AgentRun::query()
            ->selectRaw('status, count(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        $recentFailures = AgentRun::query()
            ->where('status', AgentRun::STATUS_FAILED)
            ->where('created_at', '>=', now()->subDay())
            ->count();

        return [
            'total' => (int) $byStatus->sum(),
            'by_status' => $byStatus->all(),
            'recent_failures' => $recentFailures,
            'hannah_runs' => AgentRun::where('orchestrator', AgentRun::ORCHESTRATOR_HANNAH)->count(),
        ];
    }

    protected function flowMetrics(): array
    {
        if (! Schema::hasTable('flow_runs')) {
            return ['total' => 0, 'by_status' => [], 'recent_failures' => 0];
        }

        $byStatus = FlowRun::query()
            ->selectRaw('status, count(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        $recentFailures = FlowRun::query()
            ->where('status', 'failed')
            ->where('created_at', '>=', now()->subDay())
            ->count();

        return [
            'total' => (int) $byStatus->sum(),
            'by_status' => $byStatus->all(),
            'recent_failures' => $recentFailures,
        ];
    }

    protected function integrationMetrics(): array
    {
        if (! Schema::hasTable('integrations')) {
            return ['total' => 0, 'connected' => 0, 'by_type' => []];
        }

        $all = Integration::all(['type', 'status']);
        $byType = $all->groupBy('type')->map->count();

        return [
            'total' => $all->count(),
            'connected' => $all->where('status', Integration::STATUS_CONNECTED)->count(),
            'by_type' => $byType->all(),
        ];
    }

    protected function activityMetrics(): array
    {
        $audit24h = 0;
        if (Schema::hasTable('events')) {
            $audit24h = DB::table('events')
                ->where('tenant_id', TenantContext::tenantId())
                ->where('created_at', '>=', now()->subDay())
                ->count();
        }

        $activities24h = Schema::hasTable('activities')
            ? Activity::where('created_at', '>=', now()->subDay())->count()
            : 0;

        return [
            'audit_events_24h' => $audit24h,
            'timeline_entries_24h' => $activities24h,
        ];
    }

    protected function aiMetrics(): array
    {
        $tenantId = TenantContext::tenantId();
        $prefix = sprintf('ai_cost:%s:', $tenantId ?? 'platform');
        $spentToday = 0.0;

        // Sum all cache keys for this tenant today (best-effort).
        try {
            $keys = Cache::get($prefix.'*');
            // Array driver won't support wildcard; estimate from a rolling total key if set.
            $spentToday = (float) Cache::get($prefix.'total:'.date('Y-m-d'), 0);
        } catch (\Throwable $e) {
            $spentToday = 0.0;
        }

        return [
            'estimated_cost_today_usd' => round($spentToday, 4),
            'daily_cap_usd' => (float) config('ai.daily_cost_cap', 0.5),
        ];
    }
}
