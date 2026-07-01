<?php

namespace Tests\Feature;

use App\Models\Integration;
use App\Models\ObjectType;
use App\Models\User;
use App\Models\View;
use App\Services\ForecastService;
use App\Services\HannahService;
use App\Services\IntegrationHub;
use App\Services\MonitoringService;
use App\Services\RecordWriter;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * End-to-end smoke coverage across Phases 1–9 (in-memory DB via RefreshDatabase).
 */
class UpgradeSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_upgrade_smoke(): void
    {
        $this->seed();

        $user = User::where('email', 'admin@spidernetos.com')->firstOrFail();
        Sanctum::actingAs($user);
        Auth::setUser($user);

        $this->assertGreaterThanOrEqual(3, ObjectType::count());

        $deals = ObjectType::where('slug', 'deals')->firstOrFail();
        $record = app(RecordWriter::class)->create($deals, [
            'name' => 'Upgrade Smoke Deal',
            'value' => 25000,
            'stage' => 'Qualified',
        ]);
        $this->assertGreaterThan(0, $record->id);

        $summary = app(ReportService::class)->summary();
        $this->assertGreaterThan(0, $summary['totals']['records']);

        $forecast = app(ForecastService::class)->forecast('deals');
        $this->assertTrue($forecast['available']);

        View::firstOrCreate(
            ['object_type_id' => $deals->id, 'slug' => 'smoke-view'],
            ['name' => 'Smoke', 'type' => 'table', 'config' => []]
        );

        $hannah = app(HannahService::class)->orchestrate('Smoke test goal');
        $this->assertContains($hannah->status, ['success', 'failed']);

        $integration = Integration::firstOrCreate(
            ['tenant_id' => $user->tenant_id, 'slug' => 'smoke-slack'],
            ['name' => 'Smoke', 'type' => 'slack', 'credentials' => ['webhook_url' => 'https://hooks.slack.com/test']]
        );
        $this->assertTrue(app(IntegrationHub::class)->test($integration)['ok']);

        $monitor = app(MonitoringService::class)->snapshot();
        $this->assertSame('ok', $monitor['system']['database']);

        $this->getJson('/api/monitoring')->assertOk();
        $this->getJson('/api/reports/forecast')->assertOk();
        $this->postJson('/api/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize'])->assertOk();
    }
}
