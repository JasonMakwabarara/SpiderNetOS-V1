<?php

namespace App\Http\Controllers;

use App\Models\Integration;
use App\Services\EventLogger;
use App\Services\Integrations\HubSpotOAuth;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class IntegrationOAuthController extends Controller
{
    public function __construct(protected HubSpotOAuth $hubspot)
    {
    }

    /**
     * Begin HubSpot OAuth — returns the provider authorization URL.
     */
    public function startHubSpot(Request $request)
    {
        if (! config('services.hubspot.client_id') || ! config('services.hubspot.client_secret')) {
            return response()->json([
                'error' => 'HubSpot OAuth is not configured. Set HUBSPOT_CLIENT_ID and HUBSPOT_CLIENT_SECRET.',
            ], 503);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'config' => ['nullable', 'array'],
            'integration_id' => ['nullable', 'integer'],
        ]);

        $tenantId = TenantContext::tenantId();
        $state = Str::random(40);

        Cache::put("oauth:hubspot:{$state}", [
            'tenant_id' => $tenantId,
            'user_id' => $request->user()->id,
            'name' => $validated['name'],
            'config' => $validated['config'] ?? ['target_object' => 'people'],
            'integration_id' => $validated['integration_id'] ?? null,
        ], now()->addMinutes(15));

        return response()->json([
            'url' => $this->hubspot->authorizationUrl($state),
            'state' => $state,
        ]);
    }

    /**
     * HubSpot redirects here after the user authorizes. Public route; tenant is
     * recovered from the cached OAuth state.
     */
    public function callbackHubSpot(Request $request)
    {
        $cockpitUrl = rtrim(config('services.cockpit.url', 'http://localhost:5173'), '/');

        if ($request->query('error')) {
            return redirect("{$cockpitUrl}/integrations?oauth=hubspot&status=error&message=".urlencode((string) $request->query('error_description', $request->query('error'))));
        }

        $state = (string) $request->query('state', '');
        $code = (string) $request->query('code', '');

        $pending = $state !== '' ? Cache::pull("oauth:hubspot:{$state}") : null;
        if (! $pending || $code === '') {
            return redirect("{$cockpitUrl}/integrations?oauth=hubspot&status=error&message=invalid_state");
        }

        try {
            $tokens = $this->hubspot->exchangeCode($code);

            $integration = TenantContext::withTenant($pending['tenant_id'], function () use ($pending, $tokens) {
                if (! empty($pending['integration_id'])) {
                    $integration = Integration::find($pending['integration_id']);
                    if (! $integration || $integration->type !== Integration::TYPE_HUBSPOT) {
                        throw new \RuntimeException('Integration not found.');
                    }
                    $integration->update([
                        'credentials' => array_merge($integration->credentials ?? [], $tokens, [
                            'oauth_provider' => 'hubspot',
                        ]),
                        'config' => array_merge($integration->config ?? [], $pending['config'] ?? []),
                        'status' => Integration::STATUS_CONNECTED,
                        'last_error' => null,
                    ]);

                    return $integration->fresh();
                }

                return Integration::create([
                    'name' => $pending['name'],
                    'slug' => Str::slug($pending['name']).'-'.Str::random(5),
                    'type' => Integration::TYPE_HUBSPOT,
                    'status' => Integration::STATUS_CONNECTED,
                    'credentials' => array_merge($tokens, ['oauth_provider' => 'hubspot']),
                    'config' => $pending['config'] ?? ['target_object' => 'people'],
                ]);
            });

            EventLogger::log('integration.oauth_connected', (string) $integration->id, [
                'type' => Integration::TYPE_HUBSPOT,
                'portal_id' => $tokens['portal_id'] ?? null,
            ]);

            return redirect("{$cockpitUrl}/integrations?oauth=hubspot&status=success&integration_id={$integration->id}");
        } catch (\Throwable $e) {
            return redirect("{$cockpitUrl}/integrations?oauth=hubspot&status=error&message=".urlencode($e->getMessage()));
        }
    }
}
