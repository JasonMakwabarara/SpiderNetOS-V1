<?php

namespace Tests\Feature;

use App\Models\Integration;
use App\Models\Tenant;
use App\Models\User;
use App\Support\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class IntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_admin_can_create_and_test_slack_integration(): void
    {
        Http::fake(['hooks.slack.com/*' => Http::response('ok')]);

        $user = $this->tenantAdmin();
        Sanctum::actingAs($user);

        $create = $this->postJson('/api/integrations', [
            'name' => 'Team Slack',
            'type' => 'slack',
            'credentials' => ['webhook_url' => 'https://hooks.slack.com/services/test'],
        ]);

        $create->assertStatus(201);
        $id = $create->json('id');

        $test = $this->postJson("/api/integrations/{$id}/test");
        $test->assertOk()->assertJson(['ok' => true]);
    }

    public function test_member_cannot_manage_integrations(): void
    {
        $tenant = Tenant::create(['name' => 'T', 'domain' => 't.test', 'settings' => []]);
        $member = User::create([
            'name' => 'Member',
            'email' => 'm@test.com',
            'password' => Hash::make('password'),
            'tenant_id' => $tenant->id,
            'role' => UserRole::MEMBER,
        ]);
        Sanctum::actingAs($member);

        $this->postJson('/api/integrations', [
            'name' => 'X',
            'type' => 'slack',
            'credentials' => [],
        ])->assertStatus(403);
    }

    public function test_monitoring_endpoint_returns_snapshot(): void
    {
        Sanctum::actingAs($this->tenantAdmin());

        $res = $this->getJson('/api/monitoring');
        $res->assertOk()
            ->assertJsonStructure([
                'system' => ['status', 'database', 'queue_driver'],
                'agents', 'flows', 'integrations', 'activity', 'ai', 'generated_at',
            ]);
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
