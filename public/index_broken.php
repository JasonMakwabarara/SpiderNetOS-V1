<?php
// CORS Headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ==================== HELPER FUNCTIONS ====================
function getDataFile($name) {
    return __DIR__ . '/../data/' . $name . '.json';
}

function loadData($file) {
    if (!file_exists($file)) return [];
    $content = file_get_contents($file);
    return json_decode($content, true) ?: [];
}

function saveData($file, $data) {
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

// ==================== LOCAL LLM + OPENAI FALLBACK ====================
function callLLM($prompt, $model = 'gemma4:2b') {
    // First try local Ollama (skip if not installed)
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'http://localhost:11434/api/generate');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'model' => $model,
        'prompt' => $prompt,
        'stream' => false
    ]));
    curl_setopt($ch, CURLOPT_TIMEOUT, 5); // reduce timeout for local
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($httpCode === 200 && !empty($response)) {
        $data = json_decode($response, true);
        if (isset($data['response']) && !empty($data['response'])) {
            return $data['response'];
        }
    }
    
    // Fallback to OpenAI (with SSL fixes for Windows)
    $openaiKey = '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';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $openaiKey,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'model' => 'gpt-4o-mini',
        'messages' => [['role' => 'user', 'content' => $prompt]],
        'max_tokens' => 200,
        'temperature' => 0.7
    ]));
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // TEMPORARY fix for Windows SSL
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        return $data['choices'][0]['message']['content'] ?? "No response from OpenAI.";
    }
    
    return "OpenAI error (HTTP $httpCode): $curlError";
}


// Ensure data directory exists
$dataDir = __DIR__ . '/../data';
if (!is_dir($dataDir)) mkdir($dataDir, 0777, true);

$agentsFile = getDataFile('agents');
$flowsFile = getDataFile('flows');
$memoryFile = getDataFile('memory');

// Initialize data if empty
if (!file_exists($agentsFile)) {
    saveData($agentsFile, [
        ['id' => 1, 'name' => 'SalesBot Pro', 'description' => 'Lead qualification', 'status' => 'active', 'icon' => 'ðŸ¤–'],
        ['id' => 2, 'name' => 'SupportAgent', 'description' => 'Customer support', 'status' => 'active', 'icon' => 'ðŸ’¬'],
    ]);
}
if (!file_exists($flowsFile)) {
    saveData($flowsFile, [
        ['id' => 1, 'name' => 'Order Processing', 'description' => 'Process orders', 'trigger' => 'webhook', 'executions' => 1247, 'icon' => 'ðŸ“¦'],
    ]);
}
if (!file_exists($memoryFile)) {
    saveData($memoryFile, []);
}

$agents = loadData($agentsFile);
$flows = loadData($flowsFile);
$memory = loadData($memoryFile);

// Parse request
$requestUri = $_SERVER['REQUEST_URI'];
$requestUri = strtok($requestUri, '?');
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? [];

// Normalize path: remove /api prefix if present
$path = preg_replace('#^/api/#', '/', $requestUri);
$path = rtrim($path, '/');
if (empty($path)) $path = '/';

// ==================== AGENTS ====================
if (preg_match('#^/agents$#', $path) && $method === 'GET') {
    echo json_encode($agents);
    exit;
}

if (preg_match('#^/agents$#', $path) && $method === 'POST') {
    $ids = array_column($agents, 'id');
$newId = empty($ids) ? 1 : max($ids) + 1;
    $newAgent = [
        'id' => $newId,
        'name' => $input['name'] ?? 'New Agent',
        'description' => $input['description'] ?? '',
        'status' => $input['status'] ?? 'inactive',
        'icon' => $input['icon'] ?? 'ðŸ¤–',
    ];
    $agents[] = $newAgent;
    saveData($agentsFile, $agents);
    echo json_encode($newAgent);
    exit;
}

if (preg_match('#^/agents/(\d+)$#', $path, $matches) && $method === 'PUT') {
    $id = (int) $matches[1];
    foreach ($agents as &$agent) {
        if ($agent['id'] === $id) {
            $agent['name'] = $input['name'] ?? $agent['name'];
            $agent['description'] = $input['description'] ?? $agent['description'];
            $agent['status'] = $input['status'] ?? $agent['status'];
            $agent['icon'] = $input['icon'] ?? $agent['icon'];
            saveData($agentsFile, $agents);
            echo json_encode($agent);
            exit;
        }
    }
    http_response_code(404);
    echo json_encode(['error' => 'Agent not found']);
    exit;
}

if (preg_match('#^/agents/(\d+)$#', $path, $matches) && $method === 'DELETE') {
    $id = (int) $matches[1];
    foreach ($agents as $key => $agent) {
        if ($agent['id'] === $id) {
            unset($agents[$key]);
            saveData($agentsFile, array_values($agents));
            http_response_code(204);
            exit;
        }
    }
    http_response_code(404);
    echo json_encode(['error' => 'Agent not found']);
    exit;
}

if (preg_match('#^/agents/(\d+)/run$#', $path, $matches) && $method === 'POST') {
    $id = (int) $matches[1];
    foreach ($agents as $agent) {
        if ($agent['id'] === $id) {
            $message = $input['message'] ?? 'Hello';
            // Use local LLM (gemma4:2b) with fallback to OpenAI
            $aiResponse = callLLM($message, 'gemma4:2b');
            echo json_encode(['result' => ['response' => $aiResponse]]);
            exit;
        }
    }
    http_response_code(404);
    echo json_encode(['error' => 'Agent not found']);
    exit;
}

// ==================== FLOWS ====================
if (preg_match('#^/flows$#', $path) && $method === 'GET') {
    echo json_encode($flows);
    exit;
}

if (preg_match('#^/flows$#', $path) && $method === 'POST') {
    $ids = array_column($flows, 'id');
$newId = empty($ids) ? 1 : max($ids) + 1;
    $newFlow = [
        'id' => $newId,
        'name' => $input['name'] ?? 'New Flow',
        'description' => $input['description'] ?? '',
        'trigger' => $input['trigger'] ?? 'manual',
        'executions' => 0,
        'icon' => $input['icon'] ?? 'âš¡',
    ];
    $flows[] = $newFlow;
    saveData($flowsFile, $flows);
    echo json_encode($newFlow);
    exit;
}

if (preg_match('#^/flows/(\d+)$#', $path, $matches) && $method === 'PUT') {
    $id = (int) $matches[1];
    foreach ($flows as &$flow) {
        if ($flow['id'] === $id) {
            $flow['name'] = $input['name'] ?? $flow['name'];
            $flow['description'] = $input['description'] ?? $flow['description'];
            $flow['trigger'] = $input['trigger'] ?? $flow['trigger'];
            $flow['icon'] = $input['icon'] ?? $flow['icon'];
            saveData($flowsFile, $flows);
            echo json_encode($flow);
            exit;
        }
    }
    http_response_code(404);
    echo json_encode(['error' => 'Flow not found']);
    exit;
}

if (preg_match('#^/flows/(\d+)$#', $path, $matches) && $method === 'DELETE') {
    $id = (int) $matches[1];
    foreach ($flows as $key => $flow) {
        if ($flow['id'] === $id) {
            unset($flows[$key]);
            saveData($flowsFile, array_values($flows));
            http_response_code(204);
            exit;
        }
    }
    http_response_code(404);
    echo json_encode(['error' => 'Flow not found']);
    exit;
}

if (preg_match('#^/flows/(\d+)/execute$#', $path, $matches) && $method === 'POST') {
    $id = (int) $matches[1];
    foreach ($flows as &$flow) {
        if ($flow['id'] === $id) {
            $flow['executions']++;
            saveData($flowsFile, $flows);
            echo json_encode(['message' => "Flow '{$flow['name']}' executed successfully", 'executions' => $flow['executions']]);
            exit;
        }
    }
    http_response_code(404);
    echo json_encode(['error' => 'Flow not found']);
    exit;
}

// ==================== ATLAS CHAT ====================
if ($path === '/atlas/chat' && $method === 'POST') {
    $text = $input['message'] ?? '';
    $lower = strtolower($text);
    
    // Commands (no AI)
    if (str_contains($lower, 'create agent')) {
        $name = trim(preg_replace('/create agent/i', '', $text));
        if (empty($name)) {
            echo json_encode(['response' => 'Please specify an agent name, e.g., "create agent SalesBot"']);
            exit;
        }
        $ids = array_column($agents, 'id');
$newId = empty($ids) ? 1 : max($ids) + 1;
        $newAgent = [
            'id' => $newId,
            'name' => $name,
            'description' => "Created via Atlas",
            'status' => 'inactive',
            'icon' => 'ðŸ¤–'
        ];
        $agents[] = $newAgent;
        saveData($agentsFile, $agents);
        echo json_encode(['response' => "âœ… Agent '$name' created successfully!"]);
        exit;
    }
    
    if (str_contains($lower, 'create flow')) {
        $name = trim(preg_replace('/create flow/i', '', $text));
        if (empty($name)) {
            echo json_encode(['response' => 'Please specify a flow name, e.g., "create flow OrderProcessing"']);
            exit;
        }
        $ids = array_column($flows, 'id');
$newId = empty($ids) ? 1 : max($ids) + 1;
        $newFlow = [
            'id' => $newId,
            'name' => $name,
            'description' => "Created via Atlas",
            'trigger' => 'manual',
            'executions' => 0,
            'icon' => 'âš¡'
        ];
        $flows[] = $newFlow;
        saveData($flowsFile, $flows);
        echo json_encode(['response' => "âœ… Flow '$name' created successfully!"]);
        exit;
    }
    
    if ($lower === '/agents' || str_contains($lower, 'list agents')) {
        if (empty($agents)) {
            echo json_encode(['response' => 'No agents found. Create one with "create agent [name]".']);
            exit;
        }
        $list = "**Your agents:**\n";
        foreach ($agents as $a) {
            $list .= "- {$a['name']} ({$a['status']})\n";
        }
        echo json_encode(['response' => $list]);
        exit;
    }
    
    if ($lower === '/flows' || str_contains($lower, 'list flows')) {
        if (empty($flows)) {
            echo json_encode(['response' => 'No flows found. Create one with "create flow [name]".']);
            exit;
        }
        $list = "**Your flows:**\n";
        foreach ($flows as $f) {
            $list .= "- {$f['name']} (runs: {$f['executions']})\n";
        }
        echo json_encode(['response' => $list]);
        exit;
    }
    
    if ($lower === '/status') {
        echo json_encode(['response' => "**System Status:**\n- Agents: " . count($agents) . "\n- Flows: " . count($flows)]);
        exit;
    }
    
    if ($lower === '/help') {
        echo json_encode(['response' => "**Available Commands:**\n\nâ€¢ `create agent [name]` - Create a new AI agent\nâ€¢ `create flow [name]` - Create a new workflow\nâ€¢ `/agents` - List all agents\nâ€¢ `/flows` - List all flows\nâ€¢ `/status` - System status\nâ€¢ `/help` - Show this help"]);
        exit;
    }
    
    // Natural language: use local LLM (gemma4:2b) with fallback
    $aiResponse = callLLM($text, 'gemma4:2b');
    echo json_encode(['response' => $aiResponse]);
    exit;
}

// ==================== RAG ====================
if ($path === '/rag/upload' && $method === 'POST') {
    $content = $input['content'] ?? '';
    $metadata = $input['metadata'] ?? [];
    $memory[] = ['id' => uniqid(), 'content' => $content, 'metadata' => $metadata, 'created_at' => date('c')];
    saveData($memoryFile, $memory);
    echo json_encode(['message' => 'Document stored successfully', 'id' => end($memory)['id']]);
    exit;
}

if ($path === '/rag/query' && $method === 'POST') {
    $query = $input['query'] ?? '';
    $results = array_filter($memory, function($m) use ($query) {
        return stripos($m['content'], $query) !== false;
    });
    $context = implode("\n\n", array_column($results, 'content'));
    
    if ($context) {
        $answer = callLLM("Context:\n$context\n\nQuestion: $query", 'gemma4:2b');
    } else {
        $answer = "I couldn't find information about '$query' in the stored documents.";
    }
    echo json_encode(['answer' => $answer, 'sources' => array_values($results)]);
    exit;
}
    echo json_encode(['answer' => $answer, 'sources' => array_values($results)]);
    exit;
}

// ==================== HEALTH ====================
if ($path === '/health' && $method === 'GET') {
    echo json_encode(['status' => 'healthy', 'time' => time()]);
    exit;
}

// ==================== LOGIN ====================
if ($path === '/login' && $method === 'POST') {
    $email = $input['email'] ?? '';
    $password = $input['password'] ?? '';
    if ($email === 'admin@spidernetos.com' && $password === 'Zukaarimoto01!') {
        echo json_encode([
            'token' => 'mock-token-' . time(),
            'user' => ['id' => 1, 'name' => 'Admin', 'email' => $email]
        ]);
        exit;
    }
    http_response_code(401);
    echo json_encode(['error' => 'Invalid credentials']);
    exit;
}

// ==================== FEATURE PACKS ====================
if ($path === '/feature-packs' && $method === 'GET') {
    echo json_encode([]);
    exit;
}

if (preg_match('#^/feature-packs/(.+)$#', $path, $matches) && $method === 'GET') {
    echo json_encode(['id' => $matches[1], 'name' => 'Example Pack', 'version' => '1.0']);
    exit;
}

// ==================== 404 ====================
http_response_code(404);
echo json_encode(['error' => 'Endpoint not found', 'path' => $path, 'method' => $method]);
