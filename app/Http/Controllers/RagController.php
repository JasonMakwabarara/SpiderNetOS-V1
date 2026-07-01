<?php

namespace App\Http\Controllers;

use App\Services\InferenceService;
use App\Services\MemoryService;
use Illuminate\Http\Request;

/**
 * Retrieval-augmented generation over tenant-scoped semantic memory.
 *
 * Replaces the previous inline closures in routes/api.php that did substring
 * matching over a flat JSON file and called OpenAI via cURL with TLS disabled.
 */
class RagController extends Controller
{
    public function __construct(
        protected MemoryService $memory,
        protected InferenceService $inference,
    ) {
    }

    public function upload(Request $request)
    {
        $validated = $request->validate([
            'content' => 'required|string',
            'metadata' => 'nullable|array',
        ]);

        $memory = $this->memory->store(
            $validated['content'],
            $validated['metadata'] ?? [],
            'rag_upload',
        );

        return response()->json(['message' => 'Document stored', 'id' => $memory->id]);
    }

    public function query(Request $request)
    {
        $validated = $request->validate([
            'query' => 'required|string',
        ]);

        $results = $this->memory->search($validated['query']);

        if ($results->isEmpty()) {
            return response()->json([
                'answer' => "I couldn't find information about '{$validated['query']}'.",
                'sources' => [],
            ]);
        }

        $context = $results->pluck('content')->implode("\n\n");
        $answer = $this->inference->chat(
            "Use the following context to answer the question.\n\nContext:\n{$context}\n\nQuestion: {$validated['query']}"
        );

        return response()->json([
            'answer' => $answer,
            'sources' => $results->values(),
        ]);
    }
}
