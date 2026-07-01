<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\AgentRun;
use App\Services\AgentDispatcher;
use App\Services\AgentWorker;
use App\Services\EventLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AgentController extends Controller
{
    public function __construct(
        protected AgentWorker $worker,
    ) {
    }

    public function index()
    {
        return response()->json(Agent::all());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'role' => 'nullable|string',
            'type' => 'nullable|string',
            'description' => 'nullable|string',
            'config' => 'nullable|array',
            'capabilities' => 'nullable|array',
        ]);

        $validated['slug'] = Str::slug($validated['name']).'-'.Str::random(6);
        $validated['status'] = $request->input('status', 'active');

        $agent = Agent::create($validated);
        EventLogger::log('agent.created', (string) $agent->id, ['name' => $agent->name]);

        return response()->json($agent, 201);
    }

    public function show(Agent $agent)
    {
        return response()->json($agent);
    }

    public function update(Request $request, Agent $agent)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string',
            'role' => 'nullable|string',
            'type' => 'nullable|string',
            'description' => 'nullable|string',
            'status' => 'sometimes|string',
            'config' => 'nullable|array',
            'capabilities' => 'nullable|array',
        ]);

        if (isset($validated['name'])) {
            $validated['slug'] = Str::slug($validated['name']).'-'.Str::random(6);
        }

        $agent->update($validated);
        EventLogger::log('agent.updated', (string) $agent->id, ['name' => $agent->name]);

        return response()->json($agent);
    }

    public function destroy(Agent $agent)
    {
        EventLogger::log('agent.deleted', (string) $agent->id, ['name' => $agent->name]);
        $agent->delete();

        return response()->json(null, 204);
    }

    public function run(Request $request, Agent $agent)
    {
        $message = $request->input('message', 'Hello');
        $context = $request->input('context', []);

        $run = AgentRun::create([
            'agent_id' => $agent->id,
            'orchestrator' => AgentRun::ORCHESTRATOR_AGENT,
            'status' => AgentRun::STATUS_RUNNING,
            'trigger' => 'manual',
            'goal' => $message,
            'context' => $context,
            'record_id' => $context['record_id'] ?? null,
            'started_at' => now(),
        ]);

        try {
            $result = AgentDispatcher::suppress(
                fn () => $this->worker->run($agent, $message, $context)
            );

            $run->update([
                'status' => AgentRun::STATUS_SUCCESS,
                'steps' => $result['steps'],
                'result' => $result['response'],
                'finished_at' => now(),
            ]);

            EventLogger::log('agent.run', (string) $agent->id, ['message' => $message]);

            return response()->json([
                'result' => ['response' => $result['response']],
                'run' => $run->fresh(),
            ]);
        } catch (\Throwable $e) {
            $run->update([
                'status' => AgentRun::STATUS_FAILED,
                'error' => $e->getMessage(),
                'finished_at' => now(),
            ]);

            return response()->json(['error' => $e->getMessage(), 'run' => $run->fresh()], 500);
        }
    }

    public function runs(Agent $agent)
    {
        return response()->json(
            $agent->runs()->latest()->limit(25)->get()
        );
    }
}
