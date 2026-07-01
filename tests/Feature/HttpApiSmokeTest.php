<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HttpApiSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_api_surface(): void
    {
        $this->seed();
        $user = User::where('email', 'admin@spidernetos.com')->firstOrFail();
        Sanctum::actingAs($user);

        $this->getJson('/api/health')->assertOk();
        $this->getJson('/api/me')->assertOk()->assertJsonPath('role', 'super_admin');
        $this->getJson('/api/objects')->assertOk();
        $this->getJson('/api/agents')->assertOk();
        $this->getJson('/api/flows')->assertOk();
        $this->getJson('/api/monitoring')->assertOk()->assertJsonStructure(['system', 'agents', 'flows']);
        $this->getJson('/api/reports/summary')->assertOk();
        $this->getJson('/api/reports/forecast')->assertOk();
        $this->getJson('/api/audit')->assertOk();
        $this->getJson('/api/integrations')->assertOk();
        $this->getJson('/api/activities')->assertOk();

        $this->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
        ])->assertOk()->assertJsonPath('result.serverInfo.name', 'SpiderNetOS');
    }

    public function test_login_and_logout(): void
    {
        $this->seed();

        $login = $this->postJson('/api/login', [
            'email' => 'admin@spidernetos.com',
            'password' => 'change-me-on-first-login',
        ]);
        $login->assertOk()->assertJsonStructure(['token', 'user']);

        $token = $login->json('token');
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/logout')
            ->assertOk();
    }
}
