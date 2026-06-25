<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->string('type')->nullable()->after('slug');
            $table->foreignId('pack_id')->nullable()->after('config')
                ->constrained('feature_packs')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pack_id');
            $table->dropColumn('type');
        });
    }
};
