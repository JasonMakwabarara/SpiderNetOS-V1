<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attributes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('object_type_id')->constrained('object_types')->cascadeOnDelete();
            $table->string('slug');
            $table->string('name');
            // text|number|currency|date|datetime|checkbox|select|multiselect|
            // relationship|email|url|ai
            $table->string('type')->default('text');
            // Type-specific settings: select options, relationship target object,
            // currency code, AI prompt/output, etc.
            $table->json('config')->nullable();
            $table->boolean('is_required')->default(false);
            $table->boolean('is_unique')->default(false);
            // Whether this attribute should be shown as a primary/title field.
            $table->boolean('is_title')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['object_type_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attributes');
    }
};
