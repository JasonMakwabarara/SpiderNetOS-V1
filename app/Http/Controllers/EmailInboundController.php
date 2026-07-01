<?php

namespace App\Http\Controllers;

use App\Models\Integration;
use App\Services\EmailInboundService;
use App\Support\TenantContext;
use Illuminate\Http\Request;

/**
 * Public inbound email webhook. The opaque per-integration token is the
 * credential; tenant context is established from the matched integration.
 */
class EmailInboundController extends Controller
{
    public function __construct(protected EmailInboundService $inbound)
    {
    }

    public function handle(Request $request, string $token)
    {
        $integration = Integration::withoutGlobalScopes()
            ->where('inbound_token', $token)
            ->where('type', Integration::TYPE_EMAIL)
            ->first();

        if (! $integration) {
            return response()->json(['error' => 'Unknown email inbound endpoint.'], 404);
        }

        $parsed = $this->inbound->parse($request);

        $result = TenantContext::withTenant($integration->tenant_id, function () use ($integration, $parsed) {
            return $this->inbound->handle($integration, $parsed);
        });

        return response()->json($result, $result['ok'] ? 202 : 422);
    }
}
