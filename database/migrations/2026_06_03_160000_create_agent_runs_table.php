<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained('agents')->nullOnDelete();
            $table->foreignId('record_id')->nullable()->constrained('records')->nullOnDelete();
            // hannah | agent | autonomous
            $table->string('orchestrator')->default('agent');
            $table->string('status')->default('pending'); // pending|running|success|failed
            $table->string('trigger')->default('manual'); // manual|record-event|orchestrate
            $table->text('goal')->nullable();
            $table->json('context')->nullable();
            $table->json('steps')->nullable();
            $table->text('result')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'orchestrator', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_runs');
    }
};
