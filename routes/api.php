<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AgentController;
use App\Http\Controllers\FlowController;
use App\Http\Controllers\FeaturePackController;
use App\Http\Controllers\AtlasController;

// Health check
Route::get('/health', [HealthController::class, 'index']);

// Authentication
Route::post('/login', [AuthController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    
    Route::get('/agents', [AgentController::class, 'index']);
    Route::post('/agents', [AgentController::class, 'store']);
    Route::put('/agents/{agent}', [AgentController::class, 'update']);
    Route::delete('/agents/{agent}', [AgentController::class, 'destroy']);
    Route::post('/agents/{agent}/run', [AgentController::class, 'run']);
    
    Route::get('/flows', [FlowController::class, 'index']);
    Route::post('/flows', [FlowController::class, 'store']);
    Route::put('/flows/{flow}', [FlowController::class, 'update']);
    Route::delete('/flows/{flow}', [FlowController::class, 'destroy']);
    Route::post('/flows/{flow}/execute', [FlowController::class, 'execute']);
    
    Route::get('/feature-packs', [FeaturePackController::class, 'index']);
    Route::get('/feature-packs/{id}', [FeaturePackController::class, 'show']);
});

// Atlas Chat (no auth required for testing)
Route::post('/atlas/chat', [App\Http\Controllers\AtlasController::class, 'chat']);

// RAG routes
Route::post('/rag/upload', function (Illuminate\Http\Request $request) {
    $memoryFile = __DIR__ . '/../data/memory.json';
    if (!file_exists(dirname($memoryFile))) {
        mkdir(dirname($memoryFile), 0777, true);
    }
    $memory = file_exists($memoryFile) ? json_decode(file_get_contents($memoryFile), true) : [];
    $memory[] = [
        'id' => uniqid(),
        'content' => $request->input('content'),
        'metadata' => $request->input('metadata', []),
        'created_at' => date('c')
    ];
    file_put_contents($memoryFile, json_encode($memory, JSON_PRETTY_PRINT));
    return response()->json(['message' => 'Document stored', 'id' => end($memory)['id']]);
});

Route::post('/rag/query', function (Illuminate\Http\Request $request) {
    $query = $request->input('query');
    $memoryFile = __DIR__ . '/../data/memory.json';
    $memory = file_exists($memoryFile) ? json_decode(file_get_contents($memoryFile), true) : [];
    
    $results = array_filter($memory, function($m) use ($query) {
        return stripos($m['content'], $query) !== false;
    });
    
    $context = implode("\n\n", array_column($results, 'content'));
    
    if ($context) {
        $openaiKey = env('OPENAI_API_KEY');
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/chat/completions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $openaiKey,
            'Content-Type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'model' => 'gpt-4o-mini',
            'messages' => [['role' => 'user', 'content' => "Context:\n$context\n\nQuestion: $query"]],
            'max_tokens' => 300,
        ]));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        curl_close($ch);
        
        $data = json_decode($response, true);
        $answer = $data['choices'][0]['message']['content'] ?? "No answer found.";
    } else {
        $answer = "I couldn't find information about '$query'.";
    }
    
    return response()->json(['answer' => $answer, 'sources' => array_values($results)]);
});
Route::post("/agents/{agent}/run", [App\Http\Controllers\AgentController::class, "run"])->middleware("auth:sanctum");

