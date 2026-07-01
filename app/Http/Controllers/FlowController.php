<?php

namespace App\Http\Controllers;

use App\Models\Flow;
use App\Services\EventLogger;
use App\Services\FlowDispatcher;
use App\Services\FlowRunner;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FlowController extends Controller
{
    public function __construct(protected FlowRunner $runner)
    {
    }

    public function index()
    {
        // Tenant isolation enforced by the Flow global scope.
        return response()->json(Flow::all());
    }

    public function show(Flow $flow)
    {
        return response()->json($flow);
    }

    public function store(Request $request)
    {
        $validated = $this->validateFlow($request);
        $validated['slug'] = Str::slug($validated['name']).'-'.Str::random(6);
        $validated['webhook_token'] = $this->maybeWebhookToken($validated['trigger'] ?? 'manual', null);

        $flow = Flow::create($validated);
        EventLogger::log('flow.created', (string) $flow->id, ['name' => $flow->name]);

        return response()->json($flow, 201);
    }

    public function update(Request $request, Flow $flow)
    {
        $validated = $this->validateFlow($request, partial: true);

        if (isset($validated['name'])) {
            $validated['slug'] = Str::slug($validated['name']).'-'.Str::random(6);
        }

        // (Re)issue or clear the webhook token when the trigger type changes.
        if (array_key_exists('trigger', $validated)) {
            $validated['webhook_token'] = $this->maybeWebhookToken($validated['trigger'], $flow->webhook_token);
        }

        $flow->update($validated);
        EventLogger::log('flow.updated', (string) $flow->id, ['name' => $flow->name]);

        return response()->json($flow->fresh());
    }

    public function destroy(Flow $flow)
    {
        EventLogger::log('flow.deleted', (string) $flow->id, ['name' => $flow->name]);
        $flow->delete();

        return response()->json(null, 204);
    }

    /**
     * Manual execution: run synchronously so the caller sees the step results
     * immediately. Record-event re-triggering is suppressed for inner writes.
     */
    public function execute(Request $request, Flow $flow)
    {
        $context = (array) $request->input('context', []);

        $run = FlowDispatcher::suppress(
            fn () => $this->runner->run($flow, $context, Flow::TRIGGER_MANUAL)
        );

        return response()->json([
            'message' => "Flow '{$flow->name}' executed ({$run->status})",
            'executions' => $flow->fresh()->executions,
            'run' => $run,
        ]);
    }

    public function runs(Flow $flow)
    {
        return response()->json(
            $flow->runs()->latest()->limit(25)->get()
        );
    }

    protected function validateFlow(Request $request, bool $partial = false): array
    {
        $sometimes = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'name' => [$sometimes, 'string'],
            'description' => ['nullable', 'string'],
            'trigger' => ['nullable', 'in:manual,webhook,record-event,schedule,inbound-email'],
            'trigger_config' => ['nullable', 'array'],
            'config' => ['nullable', 'array'],
            'graph' => ['nullable', 'array'],
            'is_active' => ['boolean'],
        ]);
    }

    /**
     * Webhook triggers need a token; other triggers don't. Preserves an existing
     * token when staying on the webhook trigger.
     */
    protected function maybeWebhookToken(?string $trigger, ?string $existing): ?string
    {
        if ($trigger === Flow::TRIGGER_WEBHOOK) {
            return $existing ?: Str::random(40);
        }

        return null;
    }
}
