<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Support\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_creates_tenant_and_admin(): void
    {
        $res = $this->postJson('/api/register', [
            'workspace_name' => 'Acme Corp',
            'name' => 'Jane Admin',
            'email' => 'jane@acme.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $res->assertStatus(201)
            ->assertJsonStructure(['token', 'user' => ['id', 'email', 'tenant_id', 'role']]);

        $this->assertDatabaseHas('users', ['email' => 'jane@acme.test', 'role' => UserRole::TENANT_ADMIN]);
        $this->assertDatabaseHas('tenants', ['name' => 'Acme Corp']);
    }

    public function test_change_password_requires_current_password(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user);

        $this->putJson('/api/password', [
            'current_password' => 'wrong',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])->assertStatus(422);

        $this->putJson('/api/password', [
            'current_password' => 'password',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])->assertOk();
    }

    public function test_sessions_list_and_revoke(): void
    {
        $user = $this->makeUser();
        $token = $user->createToken('test-device');
        Sanctum::actingAs($user);

        $this->getJson('/api/sessions')->assertOk()->assertJsonFragment(['name' => 'test-device']);

        $this->deleteJson('/api/sessions/'.$token->accessToken->id)->assertOk();
    }

    public function test_forgot_password_returns_generic_message(): void
    {
        $this->postJson('/api/password/forgot', ['email' => 'nobody@example.com'])
            ->assertOk()
            ->assertJsonFragment(['message' => 'If an account exists for that email, a reset link has been sent.']);
    }

    public function test_reset_password_with_valid_token(): void
    {
        $user = $this->makeUser();
        $token = Password::createToken($user);

        $this->postJson('/api/password/reset', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'brandnewpass123',
            'password_confirmation' => 'brandnewpass123',
        ])->assertOk();

        $user->refresh();
        $this->assertTrue(Hash::check('brandnewpass123', $user->password));
    }

    protected function makeUser(): User
    {
        $tenant = Tenant::create(['name' => 'T', 'domain' => 't.test', 'settings' => []]);

        return User::create([
            'name' => 'User',
            'email' => 'user@test.com',
            'password' => Hash::make('password'),
            'tenant_id' => $tenant->id,
            'role' => UserRole::TENANT_ADMIN,
        ]);
    }
}
