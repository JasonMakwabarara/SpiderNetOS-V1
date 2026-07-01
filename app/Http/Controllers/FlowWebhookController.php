<?php

namespace App\Http\Controllers;

use App\Jobs\RunFlowJob;
use App\Models\Flow;
use Illuminate\Http\Request;

/**
 * Public, unauthenticated entry point for webhook-triggered flows. The opaque
 * per-flow token is the credential; the tenant is resolved from the flow itself
 * (never from the request), preserving isolation.
 */
class FlowWebhookController extends Controller
{
    public function handle(Request $request, string $token)
    {
        // Bypass the tenant global scope: the token is globally unique and the
        // tenant is taken from the matched flow.
        $flow = Flow::withoutGlobalScopes()
            ->where('webhook_token', $token)
            ->where('trigger', Flow::TRIGGER_WEBHOOK)
            ->where('is_active', true)
            ->first();

        if (! $flow) {
            return response()->json(['error' => 'Unknown or inactive webhook.'], 404);
        }

        $context = [
            'trigger' => 'webhook',
            'payload' => $request->all(),
            'query' => $request->query(),
            'received_at' => now()->toIso8601String(),
        ];

        RunFlowJob::dispatchResilient($flow->id, $flow->tenant_id, $context, Flow::TRIGGER_WEBHOOK);

        return response()->json(['message' => 'Accepted', 'flow' => $flow->name], 202);
    }
}
