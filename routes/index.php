<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ==================== LLM Service ====================
class LLMService
{
    protected $timeout = 30;
    protected $maxRetries = 2;
    protected $openaiKey = null;

    public function __construct()
    {
        $this->openaiKey = getenv('OPENAI_API_KEY');
        if (!$this->openaiKey && file_exists(__DIR__ . '/../.env')) {
            $env = parse_ini_file(__DIR__ . '/../.env');
            $this->openaiKey = $env['OPENAI_API_KEY'] ?? null;
        }
    }

    public function chat(array $messages, string $model = null)
    {
        return $this->callOpenAI($messages);
    }

    protected function callOpenAI(array $messages)
    {
        if (!$this->openaiKey) {
            return "OpenAI API key not configured. Please add OPENAI_API_KEY to .env file.";
        }
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/chat/completions');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $this->openaiKey,
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                'model' => 'gpt-4o-mini',
                'messages' => $messages,
                'max_tokens' => 200,
                'temperature' => 0.7
            ]));
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200) {
                $data = json_decode($response, true);
                return $data['choices'][0]['message']['content'] ?? 'No response from AI';
            }
            return "OpenAI API error: HTTP $httpCode";
        } catch (Exception $e) {
            return "AI service error: " . $e->getMessage();
        }
    }
}

// ==================== Helper Functions ====================
function getTenantId() { return 'default'; }

function loadData($file) {
    if (!file_exists($file)) return [];
    $content = file_get_contents($file);
    return json_decode($content, true) ?: [];
}

function saveData($file, $data) {
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

// Ensure data directory exists
$dataDir = __DIR__ . '/../data';
if (!is_dir($dataDir)) mkdir($dataDir, 0777, true);

$agentsFile = $dataDir . '/agents.json';
$flowsFile = $dataDir . '/flows.json';
$memoryFile = $dataDir . '/memory.json';

// Initialize data if empty
if (!file_exists($agentsFile)) {
    saveData($agentsFile, [
        ['id' => 1, 'name' => 'SalesBot Pro', 'description' => 'Lead qualification', 'status' => 'active', 'icon' => '🤖'],
        ['id' => 2, 'name' => 'SupportAgent', 'description' => 'Customer support', 'status' => 'active', 'icon' => '💬'],
    ]);
}

if (!file_exists($flowsFile)) {
    saveData($flowsFile, [
        ['id' => 1, 'name' => 'Order Processing', 'description' => 'Process orders', 'trigger' => 'webhook', 'executions' => 1247, 'icon' => '📦'],
    ]);
}

if (!file_exists($memoryFile)) {
    saveData($memoryFile, []);
}

$agents = loadData($agentsFile);
$flows = loadData($flowsFile);
$memory = loadData($memoryFile);

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? [];

// ==================== AGENTS ====================
if (preg_match('#^/agents$#', $path) && $method === 'GET') {
    echo json_encode($agents);
    exit;
}

if (preg_match('#^/agents$#', $path) && $method === 'POST') {
    $newId = max(array_column($agents, 'id')) + 1;
    $newAgent = [
        'id' => $newId,
        'name' => $input['name'] ?? 'New Agent',
        'description' => $input['description'] ?? '',
        'status' => $input['status'] ?? 'inactive',
        'icon' => $input['icon'] ?? '🤖',
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
            $llm = new LLMService();
            $response = $llm->chat([
                ['role' => 'system', 'content' => "You are {$agent['name']}. Be helpful and concise."],
                ['role' => 'user', 'content' => $message]
            ]);
            echo json_encode(['result' => ['response' => $response]]);
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
            echo json_encode(['message' => "Flow {$flow['name']} executed successfully", 'executions' => $flow['executions']]);
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
    
    if (str_contains($lower, 'create agent')) {
        $name = trim(preg_replace('/create agent/i', '', $text));
        $newId = max(array_column($agents, 'id')) + 1;
        $newAgent = ['id' => $newId, 'name' => $name, 'description' => "Created via Atlas", 'status' => 'inactive', 'icon' => '🤖'];
        $agents[] = $newAgent;
        saveData($agentsFile, $agents);
        echo json_encode(['response' => "✅ Agent '$name' created successfully!"]);
        exit;
    }
    if (str_contains($lower, 'create flow')) {
        $name = trim(preg_replace('/create flow/i', '', $text));
        $newId = max(array_column($flows, 'id')) + 1;
        $newFlow = ['id' => $newId, 'name' => $name, 'description' => "Created via Atlas", 'trigger' => 'manual', 'executions' => 0, 'icon' => '⚡'];
        $flows[] = $newFlow;
        saveData($flowsFile, $flows);
        echo json_encode(['response' => "✅ Flow '$name' created successfully!"]);
        exit;
    }
    if (str_contains($lower, '/agents') || str_contains($lower, 'list agents')) {
        $list = "**Your agents:**\n";
        foreach ($agents as $a) $list .= "- {$a['name']} ({$a['status']})\n";
        echo json_encode(['response' => $list ?: 'No agents yet.']);
        exit;
    }
    if (str_contains($lower, '/flows') || str_contains($lower, 'list flows')) {
        $list = "**Your flows:**\n";
        foreach ($flows as $f) $list .= "- {$f['name']} (runs: {$f['executions']})\n";
        echo json_encode(['response' => $list ?: 'No flows yet.']);
        exit;
    }
    if (str_contains($lower, '/status')) {
        echo json_encode(['response' => "System Status:\n- Agents: " . count($agents) . "\n- Flows: " . count($flows)]);
        exit;
    }
    if (str_contains($lower, '/help')) {
        echo json_encode(['response' => "Available Commands:\n- create agent [name]\n- create flow [name]\n- /agents\n- /flows\n- /status\n- /help"]);
        exit;
    }
    // Natural language response
    $llm = new LLMService();
    $response = $llm->chat([
        ['role' => 'system', 'content' => 'You are Atlas, an AI operations assistant. Be concise and helpful.'],
        ['role' => 'user', 'content' => $text]
    ]);
    echo json_encode(['response' => $response]);
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
    $llm = new LLMService();
    $answer = $llm->chat([
        ['role' => 'system', 'content' => 'Answer based on the context provided. If the answer is not in the context, say "I don\'t know".'],
        ['role' => 'user', 'content' => "Context:\n$context\n\nQuestion: $query"]
    ]);
    echo json_encode(['answer' => $answer, 'sources' => array_values($results)]);
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

// ==================== HEALTH ====================
if ($path === '/health' && $method === 'GET') {
    echo json_encode(['status' => 'healthy', 'timestamp' => time()]);
    exit;
}

// ==================== LOGIN ====================
if ($path === '/login' && $method === 'POST') {
    $email = $input['email'] ?? '';
    $password = $input['password'] ?? '';
    if ($email === 'admin@spidernetos.com' && $password === 'Zukaarimoto01!') {
        echo json_encode(['token' => 'mock-token-' . time(), 'user' => ['id' => 1, 'name' => 'Admin', 'email' => $email]]);
        exit;
    }
    http_response_code(401);
    echo json_encode(['error' => 'Invalid credentials']);
    exit;
}

// ==================== 404 ====================
http_response_code(404);
echo json_encode(['error' => 'Endpoint not found', 'path' => $path, 'method' => $method]);