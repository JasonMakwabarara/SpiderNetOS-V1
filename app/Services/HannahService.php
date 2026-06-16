<?php
namespace App\Services;
use OpenAI;

class HannahService extends LLMService
{
    public function chat($message, $context = [])
    {
        $messages = [
            ['role' => 'system', 'content' => 'You are Hannah, a helpful AI assistant.'],
            ['role' => 'user', 'content' => $message]
        ];
        return $this->chat($messages, 'gpt-4o-mini');
    }
}
