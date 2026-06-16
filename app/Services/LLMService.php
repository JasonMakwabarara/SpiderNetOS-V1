<?php
namespace App\Services;
use OpenAI;
use Illuminate\Support\Facades\Log;
use Exception;

class LLMService
{
    protected $client;
    protected $maxRetries = 3;
    protected $costLimit = 0.50;

    public function __construct()
    {
        $this->client = OpenAI::client(env('OPENAI_API_KEY'));
    }

    public function chat(array $messages, string $model = 'gpt-3.5-turbo')
    {
        $retries = 0;
        while ($retries < $this->maxRetries) {
            try {
                $response = $this->client->chat()->create([
                    'model' => $model,
                    'messages' => $messages,
                ]);
                return $response->choices[0]->message->content;
            } catch (\Exception $e) {
                $retries++;
                Log::warning("LLM retry {$retries}");
                if ($retries >= $this->maxRetries) throw $e;
                sleep(1);
            }
        }
    }
}
