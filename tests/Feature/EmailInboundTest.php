<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Flow;
use App\Models\Integration;
use App\Models\ObjectType;
use App\Models\Record;
use App\Models\Tenant;
use App\Models\User;
use App\Support\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmailInboundTest extends TestCase
{
    use RefreshDatabase;

    public function test_inbound_email_webhook_creates_activity_and_matches_record(): void
    {
        $tenant = Tenant::create(['name' => 'T', 'domain' => 't.test', 'settings' => []]);
        $object = ObjectType::create([
            'tenant_id' => $tenant->id,
            'name' => 'People',
            'slug' => 'people',
            'icon' => '👤',
        ]);
        $record = Record::create([
            'tenant_id' => $tenant->id,
            'object_type_id' => $object->id,
            'data' => ['name' => 'Jane', 'email' => 'jane@example.com'],
        ]);

        $integration = Integration::create([
            'tenant_id' => $tenant->id,
            'name' => 'Inbox',
            'slug' => 'inbox-abc',
            'type' => Integration::TYPE_EMAIL,
            'status' => Integration::STATUS_CONNECTED,
            'inbound_token' => 'email-token-123',
            'credentials' => ['from' => 'noreply@spidernetos.com'],
            'config' => ['email_match_object' => 'people', 'email_match_field' => 'email'],
        ]);

        $response = $this->postJson('/api/hooks/email/'.$integration->inbound_token, [
            'from' => 'Jane Doe <jane@example.com>',
            'to' => 'inbox@inbound.test',
            'subject' => 'Hello team',
            'text' => 'Checking in about the deal.',
            'message_id' => '<msg-001@test>',
        ]);

        $response->assertStatus(202)->assertJson(['ok' => true]);

        $this->assertDatabaseHas('activities', [
            'tenant_id' => $tenant->id,
            'record_id' => $record->id,
            'type' => 'email',
            'title' => 'Hello team',
        ]);
    }

    public function test_inbound_email_duplicate_message_is_ignored(): void
    {
        $tenant = Tenant::create(['name' => 'T', 'domain' => 't.test', 'settings' => []]);

        $integration = Integration::create([
            'tenant_id' => $tenant->id,
            'name' => 'Inbox',
            'slug' => 'inbox-dup',
            'type' => Integration::TYPE_EMAIL,
            'status' => Integration::STATUS_CONNECTED,
            'inbound_token' => 'dup-token',
            'credentials' => [],
            'config' => [],
        ]);

        $payload = [
            'from' => 'a@example.com',
            'subject' => 'Once',
            'text' => 'Body',
            'message_id' => '<dup@test>',
        ];

        $this->postJson('/api/hooks/email/dup-token', $payload)->assertStatus(202);
        $this->postJson('/api/hooks/email/dup-token', $payload)->assertStatus(202);

        $this->assertEquals(1, Activity::withoutGlobalScope('tenant')->where('type', 'email')->count());
    }

    public function test_inbound_email_triggers_matching_flow(): void
    {
        $tenant = Tenant::create(['name' => 'T', 'domain' => 't.test', 'settings' => []]);

        $integration = Integration::create([
            'tenant_id' => $tenant->id,
            'name' => 'Inbox',
            'slug' => 'inbox-flow',
            'type' => Integration::TYPE_EMAIL,
            'status' => Integration::STATUS_CONNECTED,
            'inbound_token' => 'flow-token',
            'credentials' => [],
            'config' => [],
        ]);

        Flow::create([
            'tenant_id' => $tenant->id,
            'name' => 'Email autoresponder',
            'slug' => 'email-auto',
            'trigger' => Flow::TRIGGER_INBOUND_EMAIL,
            'trigger_config' => ['integration_id' => $integration->id],
            'config' => ['actions' => [['type' => 'log', 'message' => 'got mail']]],
            'is_active' => true,
        ]);

        $this->postJson('/api/hooks/email/flow-token', [
            'from' => 'lead@example.com',
            'subject' => 'Inbound',
            'text' => 'Hi',
        ])->assertStatus(202);

        $this->assertDatabaseHas('flow_runs', [
            'trigger' => Flow::TRIGGER_INBOUND_EMAIL,
        ]);
    }
}
