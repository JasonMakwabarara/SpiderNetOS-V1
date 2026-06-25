<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $dimension = (int) config('ai.embedding_dimension', 1536);
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
        }

        Schema::create('memories', function (Blueprint $table) use ($driver) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('source_type')->nullable();
            $table->string('source_id')->nullable();
            $table->text('content');
            // The pgvector `embedding` column is added below via raw SQL so the
            // dimension can be configured. On non-pgsql drivers (e.g. sqlite in
            // tests) fall back to JSON so migrations still run.
            if ($driver !== 'pgsql') {
                $table->json('embedding')->nullable();
            }
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'source_type', 'source_id']);
        });

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE memories ADD COLUMN embedding vector({$dimension})");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('memories');
    }
};
