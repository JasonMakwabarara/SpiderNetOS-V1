<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flow_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('flow_id')->constrained('flows')->cascadeOnDelete();
            // pending | running | success | failed
            $table->string('status')->default('pending');
            // How the run was triggered: manual | webhook | record-event | schedule
            $table->string('trigger')->default('manual');
            $table->json('context')->nullable();
            // Ordered per-node step log.
            $table->json('steps')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'flow_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flow_runs');
    }
};
