<?php

namespace App\Services;

use App\Models\Interaction;
use App\Models\MemoryProfile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * The Advanced Memory System: persists user/assistant interactions, embeds them
 * into the semantic store for long-term recall, and maintains a per-user memory
 * profile (facts the assistant has learned) that is injected into prompts.
 */
class ConversationMemory
{
    public function __construct(protected MemoryService $memory)
    {
    }

    /**
     * Record an exchange and embed a summary for semantic recall.
     */
    public function record(string $prompt, ?string $response, string $source = 'atlas', ?int $agentId = null, array $metadata = []): Interaction
    {
        $interaction = Interaction::create([
            'user_id' => Auth::id(),
            'agent_id' => $agentId,
            'source' => $source,
            'prompt' => $prompt,
            'response' => $response,
            'metadata' => $metadata ?: null,
        ]);

        // Best-effort embedding so future questions can recall this exchange.
        try {
            $this->memory->store(
                "User: {$prompt}\nAssistant: ".($response ?? ''),
                ['type' => 'interaction', 'source' => $source, 'interaction_id' => $interaction->id],
                'interaction',
                (string) $interaction->id,
            );
        } catch (\Throwable $e) {
            Log::warning('ConversationMemory: failed to embed interaction', ['error' => $e->getMessage()]);
        }

        return $interaction;
    }

    /**
     * The current user's memory profile, created on first use.
     */
    public function profile(): MemoryProfile
    {
        return MemoryProfile::firstOrCreate(['user_id' => Auth::id()], ['facts' => []]);
    }

    /**
     * Persist a discrete fact about the user (de-duplicated).
     */
    public function remember(string $fact): void
    {
        $fact = trim($fact);
        if ($fact === '') {
            return;
        }

        $profile = $this->profile();
        $facts = $profile->facts ?? [];

        foreach ($facts as $existing) {
            if (strcasecmp($existing['fact'] ?? '', $fact) === 0) {
                return;
            }
        }

        $facts[] = ['fact' => $fact, 'at' => now()->toIso8601String()];
        $profile->facts = array_slice($facts, -50); // cap growth
        $profile->save();
    }

    /**
     * Build the memory context block injected into the assistant system prompt:
     * known facts plus the most recent interactions for this user.
     */
    public function contextForPrompt(int $recent = 5): string
    {
        $parts = [];

        $facts = collect($this->profile()->facts ?? [])
            ->pluck('fact')
            ->filter()
            ->take(20);

        if ($facts->isNotEmpty()) {
            $parts[] = "What you know about this user:\n- ".$facts->implode("\n- ");
        }

        $interactions = Interaction::query()
            ->where('user_id', Auth::id())
            ->latest()
            ->limit($recent)
            ->get()
            ->reverse();

        if ($interactions->isNotEmpty()) {
            $lines = $interactions->map(function (Interaction $i) {
                $resp = $i->response ? mb_strimwidth($i->response, 0, 200, '…') : '';

                return "- Q: {$i->prompt}\n  A: {$resp}";
            })->implode("\n");
            $parts[] = "Recent conversation history:\n{$lines}";
        }

        return implode("\n\n", $parts);
    }
}
