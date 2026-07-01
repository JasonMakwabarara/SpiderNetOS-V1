<?php

namespace App\Services;

use App\Models\Agent;
use Illuminate\Support\Facades\Log;

/**
 * Executes a single role agent inside an LLM tool-calling loop. Agents can read
 * and modify records, search memory, and trigger flows via {@see AtlasTools}.
 */
class AgentWorker
{
    protected int $maxIterations = 5;

    public function __construct(
        protected InferenceService $inference,
        protected AtlasTools $tools,
        protected ConversationMemory $conversation,
    ) {
    }

    /**
     * @return array{response: string, steps: array<int, array<string, mixed>>}
     */
    public function run(Agent $agent, string $task, array $context = []): array
    {
        $messages = [
            ['role' => 'system', 'content' => $this->systemPrompt($agent, $context)],
            ['role' => 'user', 'content' => $task],
        ];

        $toolDefs = $this->tools->definitions();
        $steps = [];
        $final = null;

        for ($i = 0; $i < $this->maxIterations; $i++) {
            $result = $this->inference->chatMessages($messages, [
                'tools' => $toolDefs,
                'temperature' => 0.3,
                'max_tokens' => 800,
            ]);

            $raw = $result['raw'] ?? null;
            $message = $raw['choices'][0]['message'] ?? null;

            if ($message === null) {
                $final = $result['content'] ?? 'No response from the model.';
                break;
            }

            $toolCalls = $message['tool_calls'] ?? [];

            if (empty($toolCalls)) {
                $final = $message['content'] ?? $result['content'];
                break;
            }

            $messages[] = $message;

            foreach ($toolCalls as $call) {
                $name = $call['function']['name'] ?? '';
                $args = $this->decodeArgs($call['function']['arguments'] ?? '{}');
                $output = $this->tools->execute($name, $args);

                Log::info('Agent tool call', ['agent' => $agent->id, 'tool' => $name, 'args' => $args]);

                $steps[] = ['tool' => $name, 'args' => $args, 'ok' => str_contains($output, '"ok":true')];

                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => $call['id'] ?? $name,
                    'content' => $output,
                ];
            }
        }

        $final = $final ?: 'Task could not be completed within the iteration limit.';

        $this->conversation->record($task, $final, 'agent', $agent->id);

        return ['response' => $final, 'steps' => $steps];
    }

    protected function decodeArgs(string $json): array
    {
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }

    protected function systemPrompt(Agent $agent, array $context): string
    {
        $base = implode("\n", [
            "You are {$agent->name}, a {$agent->role} agent in SpiderNetOS.",
            $agent->description ?: 'Complete the assigned task using the available tools.',
            'Use tools to read or change workspace data — never guess record contents.',
            'Be concise and action-oriented. Confirm what you did.',
            'Today is '.now()->toFormattedDateString().'.',
        ]);

        if ($record = $context['record'] ?? null) {
            $base .= "\n\n[Record context]\n".json_encode($record, JSON_UNESCAPED_SLASHES);
        }

        $memory = $this->conversation->contextForPrompt();
        if ($memory !== '') {
            $base .= "\n\n[Memory]\n".$memory;
        }

        return $base;
    }
}
