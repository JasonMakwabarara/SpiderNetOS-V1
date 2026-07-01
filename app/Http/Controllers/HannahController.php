<?php

namespace App\Http\Controllers;

use App\Jobs\RunHannahJob;
use App\Models\AgentRun;
use App\Services\HannahService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HannahController extends Controller
{
    public function __construct(protected HannahService $hannah)
    {
    }

    /**
     * Multi-agent orchestration: Hannah plans sub-tasks and delegates to role agents.
     */
    public function orchestrate(Request $request)
    {
        $validated = $request->validate([
            'goal' => ['required', 'string', 'max:4000'],
            'context' => ['nullable', 'array'],
            'async' => ['nullable', 'boolean'],
        ]);

        $goal = $validated['goal'];
        $context = $validated['context'] ?? [];

        if ($request->boolean('async')) {
            $run = AgentRun::create([
                'orchestrator' => AgentRun::ORCHESTRATOR_HANNAH,
                'status' => AgentRun::STATUS_PENDING,
                'trigger' => 'orchestrate',
                'goal' => $goal,
                'context' => $context,
                'record_id' => $context['record_id'] ?? null,
            ]);

            RunHannahJob::dispatchResilient($run->id, Auth::user()?->tenant_id);

            return response()->json($run, 202);
        }

        $run = $this->hannah->orchestrate($goal, $context);

        return response()->json($run);
    }

    public function runs(Request $request)
    {
        $query = AgentRun::query()
            ->where('orchestrator', AgentRun::ORCHESTRATOR_HANNAH)
            ->with('agent:id,name,role')
            ->latest();

        if ($request->has('limit')) {
            $query->limit(min((int) $request->query('limit'), 50));
        }

        return response()->json($query->get());
    }

    public function show(AgentRun $agentRun)
    {
        return response()->json($agentRun->load('agent:id,name,role'));
    }
}
