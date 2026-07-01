<?php

namespace App\Services;

use App\Jobs\SendEmailJob;
use App\Models\Flow;
use App\Models\FlowRun;
use App\Models\Integration;
use App\Models\ObjectType;
use App\Support\TenantBroadcast;
use App\Support\TenantContext;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Executes a {@see Flow}. When the flow defines a `graph`, nodes run in
 * topological order with a shared context propagated along edges (supporting
 * conditional branching); otherwise the legacy linear `config.actions` run.
 *
 * Every execution is recorded as a {@see FlowRun} with a per-node step log.
 */
class FlowRunner
{
    public function __construct(
        protected InferenceService $inference,
        protected RecordWriter $records,
        protected IntegrationHub $integrations,
    ) {
    }

    public function run(Flow $flow, array $context = [], string $trigger = 'manual'): FlowRun
    {
        $run = FlowRun::create([
            'flow_id' => $flow->id,
            'status' => FlowRun::STATUS_RUNNING,
            'trigger' => $trigger,
            'context' => $context,
            'steps' => [],
            'started_at' => now(),
        ]);

        $steps = [];

        try {
            $steps = $flow->hasGraph()
                ? $this->runGraph($flow, $context)
                : $this->runLinear($flow, $context);

            $run->update([
                'status' => FlowRun::STATUS_SUCCESS,
                'steps' => $steps,
                'finished_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('FlowRunner failed', ['flow' => $flow->id, 'error' => $e->getMessage()]);
            $run->update([
                'status' => FlowRun::STATUS_FAILED,
                'steps' => $steps,
                'error' => $e->getMessage(),
                'finished_at' => now(),
            ]);
        }

        $flow->increment('executions');
        $this->logRecordActivity($flow, $context, $run->status);

        $tenantId = TenantContext::tenantId() ?? $flow->tenant_id;
        if ($tenantId) {
            TenantBroadcast::flowRun($tenantId, $run->id, $flow->id, $run->status);
        }

        return $run->refresh();
    }

    /**
     * If a run is about a record (record-event triggers seed context.record_id),
     * drop a system entry on that record's timeline.
     */
    protected function logRecordActivity(Flow $flow, array $context, string $status): void
    {
        $recordId = $context['record_id'] ?? null;
        if (! $recordId) {
            return;
        }

        $record = \App\Models\Record::find($recordId);
        if ($record) {
            ActivityLogger::system($record, "Workflow “{$flow->name}” ran ({$status})", [
                'flow_id' => $flow->id,
                'trigger' => $context['event'] ?? $context['trigger'] ?? 'manual',
            ], '⚡');
        }
    }

    // ---- Graph (DAG) execution -------------------------------------------

    protected function runGraph(Flow $flow, array $context): array
    {
        $nodes = collect($flow->nodes())->keyBy('id');
        $edges = $flow->edges();
        $order = $this->topologicalOrder($nodes->keys()->all(), $edges);

        // A node is "active" once at least one inbound edge reaches it (or it is
        // a start node with no inbound edges). Condition nodes can block edges.
        $inbound = [];
        foreach ($edges as $e) {
            $inbound[$e['to']][] = $e;
        }

        $active = [];
        foreach ($nodes as $id => $node) {
            if (empty($inbound[$id] ?? [])) {
                $active[$id] = true; // start node
            }
        }

        $steps = [];
        $blockedEdges = []; // "from:to:branch" => true

        foreach ($order as $id) {
            $node = $nodes->get($id);
            if (! $node) {
                continue;
            }

            if (empty($active[$id])) {
                $steps[] = $this->step($node, 'skipped', 'No active inbound path');

                continue;
            }

            [$message, $patch, $branchResult] = $this->executeNode($node, $context);
            $context = array_merge($context, $patch);
            $steps[] = $this->step($node, 'ok', $message);

            // Activate downstream nodes along non-blocked edges.
            foreach ($edges as $e) {
                if ($e['from'] !== $id) {
                    continue;
                }
                $branch = $e['branch'] ?? null;
                if ($branchResult !== null && $branch !== null && $branch !== $branchResult) {
                    continue; // condition pruned this edge
                }
                $active[$e['to']] = true;
            }
        }

        return $steps;
    }

    /**
     * Kahn's algorithm. Throws on cycles.
     *
     * @param  array<int, string>  $nodeIds
     * @param  array<int, array>  $edges
     * @return array<int, string>
     */
    protected function topologicalOrder(array $nodeIds, array $edges): array
    {
        $indegree = array_fill_keys($nodeIds, 0);
        $adj = [];
        foreach ($edges as $e) {
            if (! isset($indegree[$e['to']]) || ! isset($indegree[$e['from']])) {
                continue;
            }
            $adj[$e['from']][] = $e['to'];
            $indegree[$e['to']]++;
        }

        $queue = array_keys(array_filter($indegree, fn ($d) => $d === 0));
        $order = [];

        while ($queue) {
            $n = array_shift($queue);
            $order[] = $n;
            foreach ($adj[$n] ?? [] as $m) {
                if (--$indegree[$m] === 0) {
                    $queue[] = $m;
                }
            }
        }

        if (count($order) !== count($nodeIds)) {
            throw new \RuntimeException('Workflow graph contains a cycle.');
        }

        return $order;
    }

    // ---- Linear (legacy) execution ---------------------------------------

    protected function runLinear(Flow $flow, array $context): array
    {
        $steps = [];
        foreach ($flow->config['actions'] ?? [] as $i => $action) {
            $node = ['id' => "action-{$i}", 'type' => $action['type'] ?? 'log', 'config' => $action];
            [$message] = $this->executeNode($node, $context);
            $steps[] = $this->step($node, 'ok', $message);
        }

        return $steps;
    }

    // ---- Node executors ---------------------------------------------------

    /**
     * @return array{0:string, 1:array, 2:?string}  [message, context patch, branchResult]
     */
    protected function executeNode(array $node, array $context): array
    {
        $type = $node['type'] ?? 'log';
        $config = $node['config'] ?? [];

        return match ($type) {
            'trigger', 'start' => ['Triggered', [], null],
            'email' => $this->doEmail($config, $context),
            'slack' => $this->doSlack($config, $context),
            'integration' => $this->doIntegration($config, $context),
            'webhook', 'http' => $this->doWebhook($config, $context),
            'ai' => $this->doAi($config, $context),
            'create_record' => $this->doCreateRecord($config, $context),
            'condition' => $this->doCondition($config, $context),
            'log' => ['Log: '.$this->render($config['message'] ?? '', $context), [], null],
            default => ["Unknown node type '{$type}' — skipped", [], null],
        };
    }

    protected function doEmail(array $config, array $context): array
    {
        $action = [
            'to' => $this->render($config['to'] ?? '', $context),
            'subject' => $this->render($config['subject'] ?? 'Flow notification', $context),
            'body' => $this->render($config['body'] ?? '', $context),
        ];
        if ($action['to']) {
            SendEmailJob::dispatch($action);
        }

        return ["Email queued to {$action['to']}", [], null];
    }

    protected function doSlack(array $config, array $context): array
    {
        $webhook = $config['webhook'] ?? '';
        $message = $this->render($config['message'] ?? 'Flow executed', $context);
        if ($webhook) {
            try {
                Http::timeout(10)->post($webhook, ['text' => $message]);
            } catch (\Throwable $e) {
                return ['Slack post failed: '.$e->getMessage(), [], null];
            }
        }

        return ['Slack message sent', [], null];
    }

    protected function doIntegration(array $config, array $context): array
    {
        $ref = $config['integration'] ?? $config['integration_id'] ?? '';
        $integration = is_numeric($ref)
            ? Integration::find((int) $ref)
            : Integration::where('slug', $ref)->first();

        if (! $integration) {
            return ["Integration '{$ref}' not found", [], null];
        }

        $payload = [
            'message' => $this->render($config['message'] ?? '', $context),
            'to' => $this->render($config['to'] ?? '', $context),
            'subject' => $this->render($config['subject'] ?? 'SpiderNetOS notification', $context),
            'body' => $this->render($config['body'] ?? $config['message'] ?? '', $context),
        ];

        $result = $this->integrations->send($integration, $config['action'] ?? 'send', $payload);

        return [$result['message'], ['integration_ok' => $result['ok'] ?? false], null];
    }

    protected function doWebhook(array $config, array $context): array
    {
        $url = $this->render($config['url'] ?? '', $context);
        if (! $url) {
            return ['No webhook URL configured', [], null];
        }
        $payload = $config['data'] ?? $context;
        try {
            $res = Http::timeout(15)->post($url, is_array($payload) ? $payload : ['data' => $payload]);

            return ["POST {$url} → {$res->status()}", ['last_http_status' => $res->status()], null];
        } catch (\Throwable $e) {
            return ['Webhook failed: '.$e->getMessage(), [], null];
        }
    }

    protected function doAi(array $config, array $context): array
    {
        $prompt = $this->render($config['prompt'] ?? '', $context);
        if ($prompt === '') {
            return ['AI node has no prompt', [], null];
        }

        $result = $this->inference->chatMessages([
            ['role' => 'system', 'content' => $config['system'] ?? 'You are an automation step. Be concise.'],
            ['role' => 'user', 'content' => $prompt],
        ], ['temperature' => 0.3, 'max_tokens' => 500]);

        if (($result['provider'] ?? null) === null) {
            return ['AI step skipped (no provider configured)', [], null];
        }

        $value = trim((string) ($result['content'] ?? ''));
        $key = $config['output'] ?? 'ai_output';

        return ["AI produced {$key}", [$key => $value], null];
    }

    protected function doCreateRecord(array $config, array $context): array
    {
        $slug = $config['object'] ?? '';
        $object = ObjectType::where('slug', $slug)->first();
        if (! $object) {
            return ["create_record: object '{$slug}' not found", [], null];
        }

        $data = [];
        foreach (($config['data'] ?? []) as $field => $template) {
            $data[$field] = is_string($template) ? $this->render($template, $context) : $template;
        }

        $record = $this->records->create($object, $data);

        return ["Created {$slug} #{$record->id}", ['created_record_id' => $record->id], null];
    }

    protected function doCondition(array $config, array $context): array
    {
        $left = $this->render((string) ($config['left'] ?? ''), $context);
        $right = $this->render((string) ($config['right'] ?? ''), $context);
        $op = $config['op'] ?? '==';

        $result = match ($op) {
            '==' => $left == $right,
            '!=' => $left != $right,
            '>' => (float) $left > (float) $right,
            '>=' => (float) $left >= (float) $right,
            '<' => (float) $left < (float) $right,
            '<=' => (float) $left <= (float) $right,
            'contains' => str_contains($left, $right),
            'empty' => $left === '',
            'not_empty' => $left !== '',
            default => false,
        };

        $branch = $result ? 'true' : 'false';

        return ["Condition {$left} {$op} {$right} → {$branch}", ['condition' => $result], $branch];
    }

    // ---- Helpers ----------------------------------------------------------

    /** Replace {{ key }} / {{ a.b }} placeholders with context values. */
    protected function render(string $template, array $context): string
    {
        return preg_replace_callback('/\{\{\s*([\w.]+)\s*\}\}/', function ($m) use ($context) {
            $value = Arr::get($context, $m[1], '');

            return is_scalar($value) ? (string) $value : json_encode($value);
        }, $template);
    }

    protected function step(array $node, string $status, string $message): array
    {
        return [
            'node' => $node['id'] ?? null,
            'type' => $node['type'] ?? null,
            'status' => $status,
            'message' => $message,
            'at' => now()->toIso8601String(),
        ];
    }
}
