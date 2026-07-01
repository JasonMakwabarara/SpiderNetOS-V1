<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('record_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('from_record_id')->constrained('records')->cascadeOnDelete();
            $table->foreignId('to_record_id')->constrained('records')->cascadeOnDelete();
            // The relationship attribute that produced this edge (nullable for
            // ad-hoc associations).
            $table->foreignId('attribute_id')->nullable()->constrained('attributes')->nullOnDelete();
            $table->timestamps();

            $table->unique(['from_record_id', 'to_record_id', 'attribute_id'], 'record_links_edge_unique');
            $table->index(['tenant_id', 'to_record_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('record_links');
    }
};
