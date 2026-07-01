<?php

namespace Tests\Feature;

use App\Models\Integration;
use App\Models\Tenant;
use App\Models\User;
use App\Support\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HubSpotOAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_oauth_start_requires_platform_credentials(): void
    {
        config(['services.hubspot.client_id' => null]);
        Sanctum::actingAs($this->tenantAdmin());

        $this->postJson('/api/integrations/oauth/hubspot/start', [
            'name' => 'HubSpot CRM',
        ])->assertStatus(503);
    }

    public function test_oauth_start_returns_authorization_url(): void
    {
        config([
            'services.hubspot.client_id' => 'test-client',
            'services.hubspot.client_secret' => 'test-secret',
        ]);

        Sanctum::actingAs($this->tenantAdmin());

        $res = $this->postJson('/api/integrations/oauth/hubspot/start', [
            'name' => 'HubSpot CRM',
            'config' => ['target_object' => 'people'],
        ]);

        $res->assertOk()
            ->assertJsonStructure(['url', 'state']);

        $this->assertStringContainsString('app.hubspot.com/oauth/authorize', $res->json('url'));
    }

    public function test_oauth_callback_creates_integration(): void
    {
        config([
            'services.hubspot.client_id' => 'test-client',
            'services.hubspot.client_secret' => 'test-secret',
            'services.cockpit.url' => 'http://localhost:5173',
        ]);

        $admin = $this->tenantAdmin();
        $state = 'test-state-xyz';

        Cache::put("oauth:hubspot:{$state}", [
            'tenant_id' => $admin->tenant_id,
            'user_id' => $admin->id,
            'name' => 'HubSpot CRM',
            'config' => ['target_object' => 'people'],
            'integration_id' => null,
        ], now()->addMinutes(10));

        Http::fake([
            'api.hubapi.com/oauth/v1/token' => Http::response([
                'access_token' => 'access-abc',
                'refresh_token' => 'refresh-abc',
                'expires_in' => 1800,
                'hub_id' => 12345,
            ]),
        ]);

        $response = $this->get('/api/integrations/oauth/hubspot/callback?code=auth-code&state='.$state);

        $response->assertRedirect();
        $this->assertStringContainsString('status=success', $response->headers->get('Location'));

        $integration = Integration::first();
        $this->assertNotNull($integration);
        $this->assertSame(Integration::TYPE_HUBSPOT, $integration->type);
        $this->assertSame(Integration::STATUS_CONNECTED, $integration->status);
        $this->assertSame('access-abc', $integration->credentials['access_token']);
    }

    protected function tenantAdmin(): User
    {
        $tenant = Tenant::create(['name' => 'Org', 'domain' => 'org.test', 'settings' => []]);

        return User::create([
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => Hash::make('password'),
            'tenant_id' => $tenant->id,
            'role' => UserRole::TENANT_ADMIN,
        ]);
    }
}
