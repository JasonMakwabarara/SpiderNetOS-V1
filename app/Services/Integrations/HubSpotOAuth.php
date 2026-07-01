<?php

namespace App\Services\Integrations;

use App\Models\Integration;
use Illuminate\Support\Facades\Http;

/**
 * HubSpot OAuth 2.0: authorization URLs, code exchange, and token refresh.
 * Platform client credentials live in config/services.php; per-tenant tokens
 * are stored encrypted on the Integration model.
 */
class HubSpotOAuth
{
    protected const AUTH_URL = 'https://app.hubspot.com/oauth/authorize';

    protected const TOKEN_URL = 'https://api.hubapi.com/oauth/v1/token';

    /** @return array<int, string> */
    public function scopes(): array
    {
        return config('services.hubspot.scopes', [
            'crm.objects.contacts.read',
            'oauth',
        ]);
    }

    public function authorizationUrl(string $state): string
    {
        $query = http_build_query([
            'client_id' => config('services.hubspot.client_id'),
            'redirect_uri' => $this->redirectUri(),
            'scope' => implode(' ', $this->scopes()),
            'state' => $state,
        ]);

        return self::AUTH_URL.'?'.$query;
    }

    public function redirectUri(): string
    {
        return config('services.hubspot.redirect_uri')
            ?: rtrim(config('app.url'), '/').'/api/integrations/oauth/hubspot/callback';
    }

    /**
     * @return array{access_token:string,refresh_token:string,expires_at:string,portal_id?:string}
     */
    public function exchangeCode(string $code): array
    {
        $response = Http::asForm()->timeout(15)->post(self::TOKEN_URL, [
            'grant_type' => 'authorization_code',
            'client_id' => config('services.hubspot.client_id'),
            'client_secret' => config('services.hubspot.client_secret'),
            'redirect_uri' => $this->redirectUri(),
            'code' => $code,
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException('HubSpot token exchange failed: '.$response->body());
        }

        return $this->normalizeTokenResponse($response->json());
    }

    /**
     * @return array{access_token:string,refresh_token:string,expires_at:string}
     */
    public function refresh(Integration $integration): array
    {
        $refreshToken = $integration->credentials['refresh_token'] ?? '';
        if ($refreshToken === '') {
            throw new \RuntimeException('No HubSpot refresh token stored for this integration.');
        }

        $response = Http::asForm()->timeout(15)->post(self::TOKEN_URL, [
            'grant_type' => 'refresh_token',
            'client_id' => config('services.hubspot.client_id'),
            'client_secret' => config('services.hubspot.client_secret'),
            'refresh_token' => $refreshToken,
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException('HubSpot token refresh failed: '.$response->body());
        }

        return $this->normalizeTokenResponse($response->json(), $refreshToken);
    }

    /**
     * Return a valid access token, refreshing when close to expiry.
     */
    public function accessToken(Integration $integration): string
    {
        $credentials = $integration->credentials ?? [];
        $accessToken = $credentials['access_token'] ?? $credentials['api_key'] ?? '';

        if ($accessToken === '') {
            throw new \RuntimeException('HubSpot is not connected. Use OAuth to connect.');
        }

        $expiresAt = $credentials['expires_at'] ?? null;
        if ($expiresAt && now()->addMinutes(2)->gte($expiresAt) && ! empty($credentials['refresh_token'])) {
            $tokens = $this->refresh($integration);
            $integration->update([
                'credentials' => array_merge($credentials, $tokens),
                'status' => Integration::STATUS_CONNECTED,
                'last_error' => null,
            ]);
            $accessToken = $tokens['access_token'];
        }

        return $accessToken;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{access_token:string,refresh_token:string,expires_at:string,portal_id?:string}
     */
    protected function normalizeTokenResponse(array $payload, ?string $fallbackRefresh = null): array
    {
        $expiresIn = (int) ($payload['expires_in'] ?? 1800);

        return array_filter([
            'access_token' => (string) ($payload['access_token'] ?? ''),
            'refresh_token' => (string) ($payload['refresh_token'] ?? $fallbackRefresh ?? ''),
            'expires_at' => now()->addSeconds($expiresIn)->toIso8601String(),
            'portal_id' => isset($payload['hub_id']) ? (string) $payload['hub_id'] : null,
        ], fn ($v) => $v !== null && $v !== '');
    }
}
