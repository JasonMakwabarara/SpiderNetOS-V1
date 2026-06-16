<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$path = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET' && preg_match('#/api/agents$#', $path)) {
    echo json_encode([
        ['id' => 1, 'name' => 'SalesBot Pro', 'status' => 'active', 'description' => 'Lead qualification'],
        ['id' => 2, 'name' => 'SupportAgent', 'status' => 'active', 'description' => 'Customer support'],
    ]);
    exit;
}

if ($method === 'POST' && preg_match('#/api/agents$#', $path)) {
    $input = json_decode(file_get_contents('php://input'), true);
    echo json_encode([
        'id' => rand(100, 999),
        'name' => $input['name'] ?? 'New Agent',
        'status' => 'inactive',
        'description' => $input['description'] ?? '',
    ]);
    exit;
}

if ($method === 'GET' && preg_match('#/api/flows$#', $path)) {
    echo json_encode([
        ['id' => 1, 'name' => 'Order Processing', 'executions' => 1247],
    ]);
    exit;
}

if ($method === 'POST' && preg_match('#/api/flows$#', $path)) {
    $input = json_decode(file_get_contents('php://input'), true);
    echo json_encode([
        'id' => rand(100, 999),
        'name' => $input['name'] ?? 'New Flow',
        'executions' => 0,
    ]);
    exit;
}

http_response_code(404);
echo json_encode(['error' => 'Not found']);