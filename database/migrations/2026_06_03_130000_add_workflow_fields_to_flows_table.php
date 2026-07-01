<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('flows', function (Blueprint $table) {
            $table->text('description')->nullable()->after('name');
            // trigger (existing string column) holds the trigger TYPE:
            // manual | webhook | record-event | schedule.
            $table->json('trigger_config')->nullable()->after('trigger');
            // Secret token for the public webhook trigger endpoint.
            $table->string('webhook_token')->nullable()->unique()->after('trigger_config');
            // DAG definition: { nodes: [...], edges: [...] }. When present it is
            // executed topologically; otherwise config.actions runs linearly.
            $table->json('graph')->nullable()->after('config');
            $table->boolean('is_active')->default(true)->after('graph');
        });
    }

    public function down(): void
    {
        Schema::table('flows', function (Blueprint $table) {
            $table->dropColumn(['description', 'trigger_config', 'webhook_token', 'graph', 'is_active']);
        });
    }
};
