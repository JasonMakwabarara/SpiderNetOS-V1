<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use GuzzleHttp\Client;

class AgentController extends Controller
{
    protected $openaiKey;

    public function __construct()
    {
        $this->openaiKey = env('OPENAI_API_KEY');
    }

    public function index(Request $request)
    {
        $tenantId = $request->header('X-Tenant-ID', 1);
        $agents = Agent::where('tenant_id', $tenantId)->get();
        return response()->json($agents);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'role' => 'nullable|string',
            'description' => 'nullable|string'
        ]);
        
        $validated['slug'] = Str::slug($validated['name']);
        $validated['tenant_id'] = $request->header('X-Tenant-ID', 1);
        $validated['status'] = 'active';
        
        $agent = Agent::create($validated);
        return response()->json($agent, 201);
    }

    public function show(Agent $agent)
    {
        return response()->json($agent);
    }

    public function update(Request $request, Agent $agent)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string',
            'role' => 'nullable|string',
            'description' => 'nullable|string',
            'status' => 'sometimes|string'
        ]);
        
        if (isset($validated['name'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }
        
        $agent->update($validated);
        return response()->json($agent);
    }

    public function destroy(Agent $agent)
    {
        $agent->delete();
        return response()->json(null, 204);
    }

    public function run(Request $request, Agent $agent)
    {
        $message = $request->input('message', 'Hello');
        
        $systemPrompt = "You are {$agent->name}. Role: {$agent->role}. Be helpful, professional, and concise.";
        
        if (!$this->openaiKey) {
            return response()->json([
                'result' => ['response' => "OpenAI API key is missing. Please check .env file."]
            ]);
        }
        
        try {
            $client = new Client([
                'timeout' => 30,
                'verify' => false
            ]);
            
            $response = $client->post('https://api.openai.com/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->openaiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => 'gpt-4o-mini',
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $message],
                    ],
                    'max_tokens' => 200,
                ],
            ]);
            
            $body = json_decode($response->getBody(), true);
            $aiResponse = $body['choices'][0]['message']['content'] ?? "I'm not sure how to respond.";
            
            return response()->json(['result' => ['response' => $aiResponse]]);
            
        } catch (\Exception $e) {
            return response()->json([
                'result' => ['response' => "Error: " . $e->getMessage()]
            ]);
        }
    }
}