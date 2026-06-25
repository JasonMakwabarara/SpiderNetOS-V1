<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_providers', function (Blueprint $table) {
            $table->id();
            // openai | ollama | azure_openai | anthropic | custom_openai_compatible
            $table->string('type');
            $table->string('name');
            $table->string('base_url')->nullable();
            // Stored encrypted via the model's `encrypted` cast.
            $table->text('api_key')->nullable();
            $table->string('chat_model')->nullable();
            $table->string('embedding_model')->nullable();
            $table->boolean('enabled')->default(true);
            // Fallback order (lower = higher priority).
            $table->integer('priority')->default(100);
            // platform | tenant
            $table->string('scope')->default('platform');
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
            $table->json('config')->nullable();
            $table->timestamps();

            $table->index(['scope', 'enabled', 'priority']);
            $table->index(['tenant_id', 'enabled', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_providers');
    }
};
