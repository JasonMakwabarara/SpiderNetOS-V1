<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$path = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];

// In-memory storage (simulate database)
$agentsFile = __DIR__ . '/../data/agents.json';
$flowsFile = __DIR__ . '/../data/flows.json';

// Create data directory if not exists
if (!is_dir(__DIR__ . '/../data')) {
    mkdir(__DIR__ . '/../data', 0777, true);
}

// Load agents from file or initialize
if (file_exists($agentsFile)) {
    $agents = json_decode(file_get_contents($agentsFile), true);
} else {
    $agents = [
        ['id' => 1, 'name' => 'SalesBot Pro', 'description' => 'Lead qualification', 'status' => 'active', 'icon' => '📊'],
        ['id' => 2, 'name' => 'SupportAgent', 'description' => 'Customer support', 'status' => 'active', 'icon' => '💬'],
    ];
    file_put_contents($agentsFile, json_encode($agents));
}

// Load flows from file or initialize
if (file_exists($flowsFile)) {
    $flows = json_decode(file_get_contents($flowsFile), true);
} else {
    $flows = [
        ['id' => 1, 'name' => 'Order Processing', 'description' => 'Process orders', 'trigger' => 'webhook', 'executions' => 1247, 'icon' => '📦'],
        ['id' => 2, 'name' => 'Data Pipeline', 'description' => 'Sync data', 'trigger' => 'event', 'executions' => 342, 'icon' => '🔄'],
    ];
    file_put_contents($flowsFile, json_encode($flows));
}

// GET /api/agents
if ($method === 'GET' && preg_match('#/api/agents$#', $path)) {
    echo json_encode($agents);
    exit;
}

// POST /api/agents
if ($method === 'POST' && preg_match('#/api/agents$#', $path)) {
    $input = json_decode(file_get_contents('php://input'), true);
    $newId = max(array_column($agents, 'id')) + 1;
    $newAgent = [
        'id' => $newId,
        'name' => $input['name'] ?? 'New Agent',
        'description' => $input['description'] ?? '',
        'status' => $input['status'] ?? 'inactive',
        'icon' => $input['icon'] ?? '🤖',
    ];
    $agents[] = $newAgent;
    file_put_contents($agentsFile, json_encode($agents));
    echo json_encode($newAgent);
    exit;
}

// PUT /api/agents/{id}
if ($method === 'PUT' && preg_match('#/api/agents/(\d+)$#', $path, $matches)) {
    $id = (int)$matches[1];
    $input = json_decode(file_get_contents('php://input'), true);
    foreach ($agents as &$agent) {
        if ($agent['id'] === $id) {
            $agent['name'] = $input['name'] ?? $agent['name'];
            $agent['description'] = $input['description'] ?? $agent['description'];
            $agent['status'] = $input['status'] ?? $agent['status'];
            $agent['icon'] = $input['icon'] ?? $agent['icon'];
            file_put_contents($agentsFile, json_encode($agents));
            echo json_encode($agent);
            exit;
        }
    }
    http_response_code(404);
    echo json_encode(['error' => 'Agent not found']);
    exit;
}

// DELETE /api/agents/{id}
if ($method === 'DELETE' && preg_match('#/api/agents/(\d+)$#', $path, $matches)) {
    $id = (int)$matches[1];
    $found = false;
    foreach ($agents as $key => $agent) {
        if ($agent['id'] === $id) {
            unset($agents[$key]);
            $found = true;
            break;
        }
    }
    if ($found) {
        $agents = array_values($agents);
        file_put_contents($agentsFile, json_encode($agents));
        http_response_code(204);
        exit;
    }
    http_response_code(404);
    echo json_encode(['error' => 'Agent not found']);
    exit;
}

// POST /api/agents/{id}/run
if ($method === 'POST' && preg_match('#/api/agents/(\d+)/run$#', $path, $matches)) {
    $id = (int)$matches[1];
    foreach ($agents as $agent) {
        if ($agent['id'] === $id) {
            echo json_encode(['message' => "Agent '{$agent['name']}' started successfully"]);
            exit;
        }
    }
    http_response_code(404);
    echo json_encode(['error' => 'Agent not found']);
    exit;
}

// GET /api/flows
if ($method === 'GET' && preg_match('#/api/flows$#', $path)) {
    echo json_encode($flows);
    exit;
}

// POST /api/flows
if ($method === 'POST' && preg_match('#/api/flows$#', $path)) {
    $input = json_decode(file_get_contents('php://input'), true);
    $newId = max(array_column($flows, 'id')) + 1;
    $newFlow = [
        'id' => $newId,
        'name' => $input['name'] ?? 'New Flow',
        'description' => $input['description'] ?? '',
        'trigger' => $input['trigger'] ?? 'manual',
        'executions' => 0,
        'icon' => $input['icon'] ?? '⚡',
    ];
    $flows[] = $newFlow;
    file_put_contents($flowsFile, json_encode($flows));
    echo json_encode($newFlow);
    exit;
}

// PUT /api/flows/{id}
if ($method === 'PUT' && preg_match('#/api/flows/(\d+)$#', $path, $matches)) {
    $id = (int)$matches[1];
    $input = json_decode(file_get_contents('php://input'), true);
    foreach ($flows as &$flow) {
        if ($flow['id'] === $id) {
            $flow['name'] = $input['name'] ?? $flow['name'];
            $flow['description'] = $input['description'] ?? $flow['description'];
            $flow['trigger'] = $input['trigger'] ?? $flow['trigger'];
            $flow['icon'] = $input['icon'] ?? $flow['icon'];
            file_put_contents($flowsFile, json_encode($flows));
            echo json_encode($flow);
            exit;
        }
    }
    http_response_code(404);
    echo json_encode(['error' => 'Flow not found']);
    exit;
}

// DELETE /api/flows/{id}
if ($method === 'DELETE' && preg_match('#/api/flows/(\d+)$#', $path, $matches)) {
    $id = (int)$matches[1];
    $found = false;
    foreach ($flows as $key => $flow) {
        if ($flow['id'] === $id) {
            unset($flows[$key]);
            $found = true;
            break;
        }
    }
    if ($found) {
        $flows = array_values($flows);
        file_put_contents($flowsFile, json_encode($flows));
        http_response_code(204);
        exit;
    }
    http_response_code(404);
    echo json_encode(['error' => 'Flow not found']);
    exit;
}

// POST /api/flows/{id}/execute
if ($method === 'POST' && preg_match('#/api/flows/(\d+)/execute$#', $path, $matches)) {
    $id = (int)$matches[1];
    foreach ($flows as &$flow) {
        if ($flow['id'] === $id) {
            $flow['executions']++;
            file_put_contents($flowsFile, json_encode($flows));
            echo json_encode(['message' => "Flow '{$flow['name']}' executed successfully", 'executions' => $flow['executions']]);
            exit;
        }
    }
    http_response_code(404);
    echo json_encode(['error' => 'Flow not found']);
    exit;
}

// POST /api/login (mock)
if ($method === 'POST' && preg_match('#/api/login$#', $path)) {
    echo json_encode(['token' => 'mock-token-' . time(), 'user' => ['name' => 'Admin']]);
    exit;
}

// GET /api/health
if ($path === '/api/health' || strpos($path, '/health') !== false) {
    echo json_encode(['status' => 'healthy']);
    exit;
}

http_response_code(404);
echo json_encode(['error' => 'Not found']);