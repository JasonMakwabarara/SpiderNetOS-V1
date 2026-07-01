<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\AgentRun;
use App\Models\Record;
use Illuminate\Support\Facades\Log;

/**
 * Hannah — the multi-agent orchestrator. Receives a high-level goal, plans
 * sub-tasks for role-specific agents, delegates via {@see AgentWorker}, then
 * synthesises a final answer. Each orchestration is logged as an {@see AgentRun}.
 */
class HannahService
{
    public function __construct(
        protected InferenceService $inference,
        protected AgentWorker $worker,
    ) {
    }

    public function orchestrate(string $goal, array $context = [], string $trigger = 'orchestrate', ?AgentRun $existing = null): AgentRun
    {
        $run = $existing ?? AgentRun::create([
            'orchestrator' => AgentRun::ORCHESTRATOR_HANNAH,
            'status' => AgentRun::STATUS_RUNNING,
            'trigger' => $trigger,
            'goal' => $goal,
            'context' => $context,
            'record_id' => $context['record_id'] ?? null,
            'steps' => [],
            'started_at' => now(),
        ]);

        if ($existing) {
            $run->update([
                'status' => AgentRun::STATUS_RUNNING,
                'goal' => $goal,
                'context' => $context,
                'record_id' => $context['record_id'] ?? null,
                'started_at' => now(),
            ]);
        }

        $steps = [];

        try {
            $plan = $this->planTasks($goal, $context);
            $prior = [];

            foreach ($plan as $item) {
                $agent = $this->resolveAgent($item['role'] ?? '');
                if (! $agent) {
                    $steps[] = [
                        'role' => $item['role'] ?? 'unknown',
                        'status' => 'skipped',
                        'message' => 'No active agent for this role.',
                    ];

                    continue;
                }

                $task = $item['task'] ?? $goal;
                if ($prior) {
                    $task .= "\n\nOutputs from prior agents:\n".implode("\n\n", $prior);
                }

                $workerResult = AgentDispatcher::suppress(
                    fn () => $this->worker->run($agent, $task, $context)
                );

                $prior[] = "[{$agent->name} ({$agent->role})]: {$workerResult['response']}";

                $steps[] = [
                    'agent_id' => $agent->id,
                    'agent' => $agent->name,
                    'role' => $agent->role,
                    'task' => $item['task'] ?? $goal,
                    'status' => 'ok',
                    'response' => $workerResult['response'],
                    'tool_steps' => $workerResult['steps'],
                ];
            }

            $summary = $this->synthesize($goal, $steps);

            $run->update([
                'status' => AgentRun::STATUS_SUCCESS,
                'steps' => $steps,
                'result' => $summary,
                'finished_at' => now(),
            ]);

            $this->logRecordActivity($run, 'success');
        } catch (\Throwable $e) {
            Log::error('Hannah orchestration failed', ['error' => $e->getMessage()]);
            $run->update([
                'status' => AgentRun::STATUS_FAILED,
                'steps' => $steps,
                'error' => $e->getMessage(),
                'finished_at' => now(),
            ]);
            $this->logRecordActivity($run, 'failed');
        }

        return $run->refresh();
    }

    /**
     * Ask Hannah to plan which role agents should tackle the goal.
     *
     * @return array<int, array{role: string, task: string}>
     */
    protected function planTasks(string $goal, array $context): array
    {
        $roles = Agent::query()
            ->where('status', 'active')
            ->whereNotNull('role')
            ->pluck('role')
            ->unique()
            ->values()
            ->all();

        if ($roles === []) {
            return [['role' => 'Custom', 'task' => $goal]];
        }

        $roleList = implode(', ', $roles);
        $contextHint = ! empty($context['record'])
            ? "\nRecord data: ".json_encode($context['record'])
            : '';

        $prompt = implode("\n", [
            'You are Hannah, the orchestrator for SpiderNetOS.',
            "Available agent roles: {$roleList}.",
            'Break the user goal into 1-3 sub-tasks, each assigned to the best role.',
            'Return ONLY valid JSON: {"tasks":[{"role":"Sales","task":"..."}]}',
            "Goal: {$goal}{$contextHint}",
        ]);

        $raw = $this->inference->chat($prompt);
        $decoded = $this->extractJson($raw);

        if (is_array($decoded['tasks'] ?? null) && $decoded['tasks'] !== []) {
            return array_values(array_filter($decoded['tasks'], fn ($t) => is_array($t) && ! empty($t['task'])));
        }

        // Fallback: single task to the first active agent.
        $fallbackRole = $roles[0];

        return [['role' => $fallbackRole, 'task' => $goal]];
    }

    protected function synthesize(string $goal, array $steps): string
    {
        if ($steps === []) {
            return 'No agents were available to work on this goal.';
        }

        $brief = collect($steps)->map(function ($s) {
            if (($s['status'] ?? '') === 'skipped') {
                return "- {$s['role']}: skipped ({$s['message']})";
            }

            return "- {$s['agent']} ({$s['role']}): {$s['response']}";
        })->implode("\n");

        $prompt = "Summarise the outcome of this multi-agent orchestration for the user.\nGoal: {$goal}\n\nAgent outputs:\n{$brief}\n\nBe concise (2-4 sentences).";

        return $this->inference->chat($prompt);
    }

    protected function resolveAgent(string $role): ?Agent
    {
        if ($role === '') {
            return Agent::query()->where('status', 'active')->first();
        }

        return Agent::query()
            ->where('status', 'active')
            ->where(function ($q) use ($role) {
                $q->where('role', $role)->orWhere('name', $role);
            })
            ->first()
            ?? Agent::query()->where('status', 'active')->first();
    }

    protected function extractJson(string $text): ?array
    {
        if (preg_match('/\{[\s\S]*\}/', $text, $m)) {
            $decoded = json_decode($m[0], true);

            return is_array($decoded) ? $decoded : null;
        }

        return null;
    }

    protected function logRecordActivity(AgentRun $run, string $status): void
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
            ? 'Hannah orchestration completed'
            : 'Hannah orchestration failed';

        ActivityLogger::system($record, $label, [
            'run_id' => $run->id,
            'goal' => $run->goal,
        ], $icon);
    }
}
