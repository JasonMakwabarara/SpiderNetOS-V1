<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * "Ask SpiderNet" — the conversational assistant. Runs an LLM tool-calling loop:
 * the model may call {@see AtlasTools} to read/modify records, agents, flows, and
 * semantic memory, then produces a final natural-language answer. Memory context
 * (profile facts + recent interactions) is injected into the system prompt.
 *
 * Gracefully degrades on providers without tool-calling support (e.g. Ollama):
 * it simply returns the model's direct answer.
 */
class AtlasAssistant
{
    protected int $maxIterations = 5;

    public function __construct(
        protected InferenceService $inference,
        protected AtlasTools $tools,
        protected ConversationMemory $conversation,
    ) {
    }

    public function ask(string $userMessage): string
    {
        $messages = [
            ['role' => 'system', 'content' => $this->systemPrompt()],
            ['role' => 'user', 'content' => $userMessage],
        ];

        $toolDefs = $this->tools->definitions();
        $final = null;

        for ($i = 0; $i < $this->maxIterations; $i++) {
            $result = $this->inference->chatMessages($messages, [
                'tools' => $toolDefs,
                'temperature' => 0.2,
                'max_tokens' => 800,
            ]);

            $raw = $result['raw'] ?? null;
            $message = $raw['choices'][0]['message'] ?? null;

            // Provider without OpenAI-style choices (Ollama/Anthropic) — return
            // its direct content.
            if ($message === null) {
                $final = $result['content'];
                break;
            }

            $toolCalls = $message['tool_calls'] ?? [];

            if (empty($toolCalls)) {
                $final = $message['content'] ?? $result['content'];
                break;
            }

            // Append the assistant turn (with its tool calls) then each result.
            $messages[] = $message;

            foreach ($toolCalls as $call) {
                $name = $call['function']['name'] ?? '';
                $args = $this->decodeArgs($call['function']['arguments'] ?? '{}');
                $output = $this->tools->execute($name, $args);

                Log::info('Atlas tool call', ['tool' => $name, 'args' => $args]);

                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => $call['id'] ?? $name,
                    'content' => $output,
                ];
            }
        }

        $final = $final ?: "I wasn't able to complete that request.";

        // Long-term memory: persist and embed the exchange.
        $this->conversation->record($userMessage, $final, 'atlas');

        return $final;
    }

    protected function decodeArgs(string $json): array
    {
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }

    protected function systemPrompt(): string
    {
        $base = implode("\n", [
            'You are Atlas, the operator assistant for SpiderNetOS, an AI Operating System.',
            'You help the user manage their CRM data (records), AI agents, and automation workflows.',
            'Always use the provided tools to read or change data rather than guessing. ',
            'When the user states a durable preference or fact about themselves, call the remember tool.',
            'Be concise and confirm what you did, referencing record titles or ids when relevant.',
            'Today is '.now()->toFormattedDateString().'.',
        ]);

        $memory = $this->conversation->contextForPrompt();
        if ($memory !== '') {
            $base .= "\n\n[Memory]\n".$memory;
        }

        return $base;
    }
}
