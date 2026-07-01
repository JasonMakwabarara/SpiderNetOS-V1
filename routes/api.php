<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\AgentController;
use App\Http\Controllers\AiProviderController;
use App\Http\Controllers\AttributeController;
use App\Http\Controllers\AtlasController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\EmailInboundController;
use App\Http\Controllers\FeaturePackController;
use App\Http\Controllers\FlowController;
use App\Http\Controllers\FlowWebhookController;
use App\Http\Controllers\HannahController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\IntegrationController;
use App\Http\Controllers\IntegrationOAuthController;
use App\Http\Controllers\MonitoringController;
use App\Http\Controllers\McpController;
use App\Http\Controllers\ObjectTypeController;
use App\Http\Controllers\RagController;
use App\Http\Controllers\RecordController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ViewController;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\EnsureSuperAdmin;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

// Public
Route::get('/health', [HealthController::class, 'index']);
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

// Public webhook trigger for flows (authenticated by the opaque token; tenant is
// resolved from the matched flow, so this lives outside the auth/tenant group).
Route::post('/hooks/flow/{token}', [FlowWebhookController::class, 'handle']);

// Inbound email capture (token resolves tenant from the integration).
Route::post('/hooks/email/{token}', [EmailInboundController::class, 'handle']);

// OAuth provider callbacks (state binds tenant + user).
Route::get('/integrations/oauth/hubspot/callback', [IntegrationOAuthController::class, 'callbackHubSpot']);

// Authenticated. Order matters: authenticate, establish tenant context, THEN
// resolve route-model bindings (so the tenant global scope applies to bindings).
Route::middleware(['auth:sanctum', 'tenant', SubstituteBindings::class])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Agents — members can list/chat; admins manage
    Route::get('/agents', [AgentController::class, 'index']);
    Route::get('/agents/{agent}/runs', [AgentController::class, 'runs']);
    Route::post('/agents/{agent}/run', [AgentController::class, 'run']);
    Route::middleware('role:tenant_admin')->group(function () {
        Route::post('/agents', [AgentController::class, 'store']);
        Route::put('/agents/{agent}', [AgentController::class, 'update']);
        Route::delete('/agents/{agent}', [AgentController::class, 'destroy']);
    });

    // Hannah multi-agent orchestration (tenant-admin+)
    Route::middleware('role:tenant_admin')->group(function () {
        Route::post('/hannah/orchestrate', [HannahController::class, 'orchestrate']);
    });
    Route::get('/hannah/runs', [HannahController::class, 'runs']);
    Route::get('/hannah/runs/{agentRun}', [HannahController::class, 'show']);

    // Flows
    Route::get('/flows', [FlowController::class, 'index']);
    Route::get('/flows/{flow}', [FlowController::class, 'show']);
    Route::get('/flows/{flow}/runs', [FlowController::class, 'runs']);
    Route::post('/flows/{flow}/execute', [FlowController::class, 'execute']);
    Route::middleware('role:tenant_admin')->group(function () {
        Route::post('/flows', [FlowController::class, 'store']);
        Route::put('/flows/{flow}', [FlowController::class, 'update']);
        Route::delete('/flows/{flow}', [FlowController::class, 'destroy']);
    });

    // Feature packs
    Route::get('/feature-packs', [FeaturePackController::class, 'index']);
    Route::get('/feature-packs/{featurePack}', [FeaturePackController::class, 'show']);

    // Flexible data model — members use records; admins manage schema/views
    Route::apiResource('objects', ObjectTypeController::class)
        ->parameters(['objects' => 'objectType'])
        ->only(['index', 'show']);

    Route::get('objects/{objectType}/attributes', [AttributeController::class, 'index']);

    Route::get('objects/{objectType}/records', [RecordController::class, 'index']);
    Route::post('objects/{objectType}/records', [RecordController::class, 'store']);
    Route::get('records/{record}', [RecordController::class, 'show']);
    Route::put('records/{record}', [RecordController::class, 'update']);
    Route::delete('records/{record}', [RecordController::class, 'destroy']);

    Route::middleware('role:tenant_admin')->group(function () {
        Route::apiResource('objects', ObjectTypeController::class)
            ->parameters(['objects' => 'objectType'])
            ->only(['store', 'update', 'destroy']);

        Route::post('objects/{objectType}/attributes', [AttributeController::class, 'store']);
        Route::put('attributes/{attribute}', [AttributeController::class, 'update']);
        Route::delete('attributes/{attribute}', [AttributeController::class, 'destroy']);

        Route::post('views', [ViewController::class, 'store']);
        Route::put('views/{view}', [ViewController::class, 'update']);
        Route::delete('views/{view}', [ViewController::class, 'destroy']);
    });

    // Activity timeline
    Route::get('records/{record}/activities', [ActivityController::class, 'index']);
    Route::post('records/{record}/activities', [ActivityController::class, 'store']);
    Route::delete('activities/{activity}', [ActivityController::class, 'destroy']);
    Route::get('activities', [ActivityController::class, 'recent']);

    // Reporting & predictive analytics
    Route::get('reports/summary', [ReportController::class, 'summary']);
    Route::get('reports/forecast', [ReportController::class, 'forecast']);
    Route::get('reports/objects/{objectType}/group', [ReportController::class, 'group']);
    Route::get('reports/objects/{objectType}/aggregate', [ReportController::class, 'aggregate']);
    Route::get('reports/objects/{objectType}/timeseries', [ReportController::class, 'timeseries']);

    // Saved views (read for all; write for admins)
    Route::get('views', [ViewController::class, 'index']);

    // Audit log (tenant-admin+)
    Route::get('audit', [AuditController::class, 'index']);

    // MCP server (developer platform): JSON-RPC over HTTP exposing Atlas tools.
    Route::post('/mcp', [McpController::class, 'handle']);

    // Atlas assistant (now authenticated; tenant-scoped)
    Route::post('/atlas/chat', [AtlasController::class, 'chat']);

    // RAG / semantic memory (now authenticated; tenant-scoped)
    Route::post('/rag/upload', [RagController::class, 'upload']);
    Route::post('/rag/query', [RagController::class, 'query']);

    // Monitoring dashboard metrics (all authenticated users)
    Route::get('/monitoring', [MonitoringController::class, 'index']);

    // WebSocket channel authorization (Sanctum bearer token).
    Route::post('/broadcasting/auth', function (\Illuminate\Http\Request $request) {
        return Broadcast::auth($request);
    });

    // Integration hub (tenant-admin+)
    Route::middleware('role:tenant_admin')->group(function () {
        Route::get('/integrations', [IntegrationController::class, 'index']);
        Route::post('/integrations', [IntegrationController::class, 'store']);
        Route::put('/integrations/{integration}', [IntegrationController::class, 'update']);
        Route::delete('/integrations/{integration}', [IntegrationController::class, 'destroy']);
        Route::post('/integrations/{integration}/test', [IntegrationController::class, 'test']);
        Route::post('/integrations/{integration}/sync', [IntegrationController::class, 'sync']);
        Route::post('/integrations/{integration}/send', [IntegrationController::class, 'send']);
        Route::post('/integrations/oauth/hubspot/start', [IntegrationOAuthController::class, 'startHubSpot']);
    });

    // Super-admin: AI provider management (platform-scoped)
    Route::middleware(EnsureSuperAdmin::class)->group(function () {
        Route::apiResource('admin/ai-providers', AiProviderController::class)
            ->parameters(['ai-providers' => 'aiProvider']);
    });
});
