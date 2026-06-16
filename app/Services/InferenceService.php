<?php

namespace App\Services;

use App\Models\Agent;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class InferenceService
{
    protected $openaiKey;
    protected $ollamaEndpoint;
    protected $maxRetries = 3;
    protected $retryDelay = 1; // seconds between retries (exponential backoff)
    protected $timeout = 30;
    protected $costCap = 0.50; // Daily cost cap in USD
    
    public function __construct()
    {
        $this->openaiKey = env('OPENAI_API_KEY');
        $this->ollamaEndpoint = env('OLLAMA_ENDPOINT', 'http://localhost:11434');
    }
    
    public function chat($message, Agent $agent = null)
    {
        // Check daily cost cap before processing
        if (!$this->checkCostCap()) {
            Log::warning("Daily AI cost cap of \${$this->costCap} reached");
            return "Daily AI usage limit reached. Please try again tomorrow.";
        }
        
        $systemPrompt = $this->getSystemPrompt($agent);
        $fullPrompt = $systemPrompt ? "$systemPrompt\n\nUser: $message" : $message;
        
        // Try Ollama first with retries
        $response = $this->callOllamaWithRetry($fullPrompt);
        if ($response) {
            $this->trackCost(0.001); // Approximate local model cost
            return $response;
        }
        
        // Fallback to OpenAI with retries
        $response = $this->callOpenAIWithRetry($fullPrompt);
        if ($response) {
            $cost = $this->calculateOpenAICost($fullPrompt);
            $this->trackCost($cost);
            return $response;
        }
        
        return "AI service temporarily unavailable. Please try again later.";
    }
    
    protected function callOllamaWithRetry($prompt)
    {
        $lastException = null;
        
        for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
            try {
                $response = Http::timeout($this->timeout)
                    ->post("{$this->ollamaEndpoint}/api/generate", [
                        'model' => 'gemma4:2b',
                        'prompt' => $prompt,
                        'stream' => false
                    ]);
                
                if ($response->successful()) {
                    $result = $response->json()['response'] ?? null;
                    if ($result) {
                        Log::info("Ollama call succeeded on attempt $attempt");
                        return $result;
                    }
                }
                
                Log::warning("Ollama API error (attempt $attempt): " . $response->status());
                $lastException = new \Exception("HTTP " . $response->status());
                
            } catch (\Exception $e) {
                Log::warning("Ollama call failed (attempt $attempt): " . $e->getMessage());
                $lastException = $e;
            }
            
            // Exponential backoff before retry
            if ($attempt < $this->maxRetries) {
                $waitTime = $this->retryDelay * pow(2, $attempt - 1);
                Log::info("Waiting {$waitTime}s before retry $attempt");
                sleep($waitTime);
            }
        }
        
        Log::error("Ollama call failed after {$this->maxRetries} attempts");
        return null;
    }
    
    protected function callOpenAIWithRetry($prompt)
    {
        $lastException = null;
        
        for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
            try {
                $response = Http::timeout($this->timeout)
                    ->withHeaders([
                        'Authorization' => 'Bearer ' . $this->openaiKey,
                        'Content-Type' => 'application/json',
                    ])
                    ->post('https://api.openai.com/v1/chat/completions', [
                        'model' => 'gpt-4o-mini',
                        'messages' => [['role' => 'user', 'content' => $prompt]],
                        'max_tokens' => 200,
                        'temperature' => 0.7
                    ]);
                
                if ($response->successful()) {
                    $result = $response->json()['choices'][0]['message']['content'] ?? null;
                    if ($result) {
                        Log::info("OpenAI call succeeded on attempt $attempt");
                        return $result;
                    }
                }
                
                Log::warning("OpenAI API error (attempt $attempt): " . $response->status());
                $lastException = new \Exception("HTTP " . $response->status());
                
            } catch (\Exception $e) {
                Log::warning("OpenAI call failed (attempt $attempt): " . $e->getMessage());
                $lastException = $e;
            }
            
            // Exponential backoff before retry
            if ($attempt < $this->maxRetries) {
                $waitTime = $this->retryDelay * pow(2, $attempt - 1);
                Log::info("Waiting {$waitTime}s before retry $attempt");
                sleep($waitTime);
            }
        }
        
        Log::error("OpenAI call failed after {$this->maxRetries} attempts: " . ($lastException ? $lastException->getMessage() : 'Unknown error'));
        return null;
    }
    
    protected function checkCostCap()
    {
        $today = date('Y-m-d');
        $dailyCost = Cache::get("ai_cost_$today", 0);
        $withinCap = $dailyCost < $this->costCap;
        
        if (!$withinCap) {
            Log::warning("Daily cost cap reached: \${$dailyCost} / \${$this->costCap}");
        }
        
        return $withinCap;
    }
    
    protected function trackCost($cost)
    {
        $today = date('Y-m-d');
        $dailyCost = Cache::get("ai_cost_$today", 0);
        $newTotal = $dailyCost + $cost;
        Cache::put("ai_cost_$today", $newTotal, now()->endOfDay());
        
        Log::info("AI cost tracked", ['cost' => $cost, 'total' => $newTotal, 'cap' => $this->costCap]);
    }
    
    protected function calculateOpenAICost($prompt)
    {
        // Approximate token count (rough estimate: 4 chars per token)
        $tokenCount = ceil(strlen($prompt) / 4);
        // GPT-4o-mini pricing: $0.15 per 1M input tokens, $0.60 per 1M output tokens
        // Using average of $0.000000375 per token
        $costPerToken = 0.000000375;
        return $tokenCount * $costPerToken;
    }
    
    protected function getSystemPrompt($agent)
    {
        if (!$agent) {
            return "You are a helpful AI assistant. Be concise.";
        }
        
        $prompts = [
            'Sales' => "You are a Sales Assistant. Help with product inquiries, pricing, and closing deals. Be professional and persuasive.",
            'Support' => "You are a Customer Support Agent. Help resolve issues, answer FAQs, and provide troubleshooting steps. Be patient and helpful.",
            'Data Analyst' => "You are a Data Analyst. Help interpret data, create visualizations, and provide insights. Be analytical and precise.",
            'Developer' => "You are a Developer Assistant. Help with code, debugging, and best practices. Be technical and accurate.",
            'HR' => "You are an HR Assistant. Help with employee questions, policies, and onboarding. Be friendly and informative.",
        ];
        
        return $prompts[$agent->role] ?? "You are {$agent->name}. {$agent->description}";
    }
}