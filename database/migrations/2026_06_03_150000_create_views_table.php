<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('object_type_id')->constrained('object_types')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            // table | kanban
            $table->string('type')->default('table');
            // { group_by, columns[], sort, filters{} }
            $table->json('config')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'object_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('views');
    }
};
