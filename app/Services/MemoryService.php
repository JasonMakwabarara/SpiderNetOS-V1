<?php

namespace App\Services;

use App\Models\Memory;
use App\Support\TenantContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Tenant-scoped semantic memory store backed by pgvector.
 *
 * Stores content with an embedding vector and retrieves the nearest neighbours
 * by cosine distance. Falls back to a substring match on non-pgsql drivers
 * (e.g. sqlite under test) so the API still functions.
 */
class MemoryService
{
    public function __construct(protected EmbeddingService $embeddings)
    {
    }

    public function store(string $content, array $metadata = [], ?string $sourceType = null, ?string $sourceId = null): Memory
    {
        $memory = new Memory([
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'content' => $content,
            'metadata' => $metadata,
        ]);
        // tenant_id auto-set by BelongsToTenant.
        $memory->save();

        $vector = $this->embeddings->embed($content);
        if ($vector) {
            $this->writeEmbedding($memory, $vector);
        }

        return $memory->refresh();
    }

    /**
     * @return Collection<int, array{id:int, content:string, metadata:array, score:float}>
     */
    public function search(string $query, int $limit = 5): Collection
    {
        $tenantId = TenantContext::tenantId();

        if (DB::connection()->getDriverName() !== 'pgsql') {
            return $this->substringFallback($query, $limit);
        }

        $vector = $this->embeddings->embed($query);
        if (! $vector) {
            return $this->substringFallback($query, $limit);
        }

        $literal = '['.implode(',', $vector).']';

        // Cosine distance (<=>) ascending = most similar first.
        $rows = DB::table('memories')
            ->select('id', 'content', 'metadata')
            ->selectRaw('1 - (embedding <=> ?) as score', [$literal])
            ->where('tenant_id', $tenantId)
            ->whereNotNull('embedding')
            ->orderByRaw('embedding <=> ?', [$literal])
            ->limit($limit)
            ->get();

        return $rows->map(fn ($r) => [
            'id' => $r->id,
            'content' => $r->content,
            'metadata' => $r->metadata ? json_decode($r->metadata, true) : [],
            'score' => (float) $r->score,
        ]);
    }

    protected function substringFallback(string $query, int $limit): Collection
    {
        return Memory::query()
            ->where('content', 'like', '%'.$query.'%')
            ->limit($limit)
            ->get()
            ->map(fn (Memory $m) => [
                'id' => $m->id,
                'content' => $m->content,
                'metadata' => $m->metadata ?? [],
                'score' => 0.0,
            ]);
    }

    protected function writeEmbedding(Memory $memory, array $vector): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            $literal = '['.implode(',', $vector).']';
            DB::statement('UPDATE memories SET embedding = ? WHERE id = ?', [$literal, $memory->id]);
        } else {
            // JSON column fallback.
            DB::table('memories')->where('id', $memory->id)->update(['embedding' => json_encode($vector)]);
        }
    }
}
