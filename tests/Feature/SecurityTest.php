<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\Tenant;
use App\Models\User;
use App\Support\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_a_cannot_access_tenant_b_agent(): void
    {
        [$tenantA, $userA] = $this->makeTenantUser('A');
        [$tenantB, $userB] = $this->makeTenantUser('B');

        $agentB = Agent::create([
            'tenant_id' => $tenantB->id,
            'name' => 'Secret Agent',
            'slug' => 'secret-agent',
            'status' => 'active',
        ]);

        Sanctum::actingAs($userA);

        $this->putJson("/api/agents/{$agentB->id}", ['name' => 'Hacked'])->assertStatus(404);
    }

    public function test_member_cannot_create_agents(): void
    {
        [, $member] = $this->makeTenantUser('Org', UserRole::MEMBER);
        Sanctum::actingAs($member);

        $this->postJson('/api/agents', [
            'name' => 'Rogue Bot',
            'role' => 'Custom',
        ])->assertStatus(403);
    }

    public function test_tenant_admin_can_create_agents(): void
    {
        [, $admin] = $this->makeTenantUser('Org', UserRole::TENANT_ADMIN);
        Sanctum::actingAs($admin);

        $this->postJson('/api/agents', [
            'name' => 'Allowed Bot',
            'role' => 'Support',
        ])->assertStatus(201);
    }

    public function test_member_cannot_view_audit_log(): void
    {
        [, $member] = $this->makeTenantUser('Org', UserRole::MEMBER);
        Sanctum::actingAs($member);

        $this->getJson('/api/audit')->assertStatus(403);
    }

    public function test_tenant_admin_can_view_audit_log(): void
    {
        [, $admin] = $this->makeTenantUser('Org', UserRole::TENANT_ADMIN);
        Sanctum::actingAs($admin);

        $this->getJson('/api/audit')->assertOk();
    }

    public function test_tenant_header_mismatch_is_rejected(): void
    {
        [, $user] = $this->makeTenantUser('Org', UserRole::TENANT_ADMIN);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-ID', (string) ($user->tenant_id + 999))
            ->getJson('/api/agents')
            ->assertStatus(403);
    }

    public function test_unauthenticated_routes_return_401(): void
    {
        $this->getJson('/api/agents')->assertStatus(401);
        $this->postJson('/api/atlas/chat', ['message' => 'hi'])->assertStatus(401);
    }

    /** @return array{0: Tenant, 1: User} */
    protected function makeTenantUser(string $name, string $role = UserRole::TENANT_ADMIN): array
    {
        $tenant = Tenant::create([
            'name' => $name.' Tenant',
            'domain' => strtolower($name).'.test',
            'settings' => [],
        ]);

        $user = User::create([
            'name' => $name.' User',
            'email' => strtolower($name).'@test.com',
            'password' => Hash::make('password'),
            'tenant_id' => $tenant->id,
            'is_super_admin' => $role === UserRole::SUPER_ADMIN,
            'role' => $role,
        ]);

        return [$tenant, $user];
    }
}
