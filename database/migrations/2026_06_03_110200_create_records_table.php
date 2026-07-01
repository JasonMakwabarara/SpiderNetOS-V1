<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('object_type_id')->constrained('object_types')->cascadeOnDelete();
            // Attribute slug => value map. Typed values are validated against the
            // object's attribute definitions before write.
            $table->json('data')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'object_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('records');
    }
};
