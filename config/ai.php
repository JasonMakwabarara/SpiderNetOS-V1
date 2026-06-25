<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Embedding dimension
    |--------------------------------------------------------------------------
    |
    | The dimension of the pgvector `memories.embedding` column. This MUST match
    | the embedding model configured on the active embedding provider. Changing
    | it after data exists requires re-indexing.
    |
    |   text-embedding-3-small => 1536
    |   text-embedding-3-large => 3072
    |   nomic-embed-text (Ollama) => 768
    |
    */
    'embedding_dimension' => (int) env('AI_EMBEDDING_DIMENSION', 1536),

    /*
    |--------------------------------------------------------------------------
    | Daily cost cap (USD)
    |--------------------------------------------------------------------------
    |
    | Per tenant + provider daily spend cap enforced by InferenceService.
    |
    */
    'daily_cost_cap' => (float) env('AI_DAILY_COST_CAP', 0.50),

    /*
    |--------------------------------------------------------------------------
    | HTTP behaviour
    |--------------------------------------------------------------------------
    */
    'timeout' => (int) env('AI_HTTP_TIMEOUT', 30),
    'max_retries' => (int) env('AI_MAX_RETRIES', 3),
    'retry_delay' => (int) env('AI_RETRY_DELAY', 1),

];
