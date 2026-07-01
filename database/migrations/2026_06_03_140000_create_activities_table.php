<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            // The subject record. Nullable so workspace-level / agent activities can
            // appear in a global feed without a record.
            $table->foreignId('record_id')->nullable()->constrained('records')->cascadeOnDelete();
            $table->foreignId('object_type_id')->nullable()->constrained('object_types')->nullOnDelete();
            // note | call | email | meeting | task | system | …
            $table->string('type')->default('note');
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('icon')->nullable();
            $table->json('meta')->nullable();
            // Distinguishes auto-captured entries from user-authored ones.
            $table->boolean('is_system')->default(false);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'record_id']);
            $table->index(['tenant_id', 'type']);
            $table->index(['tenant_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
