<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop unique and index constraints that depend on user_id before altering
        Schema::table('configurations', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'group', 'key']);
            $table->dropIndex(['user_id', 'group']);
        });

        Schema::table('configurations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete()->after('id');
            $table->unique(['user_id', 'group', 'key']);
            $table->index(['user_id', 'group']);
        });

        // All existing configuration records become global (null user_id)
        DB::table('configurations')->update(['user_id' => null]);
    }

    public function down(): void
    {
        Schema::table('configurations', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'group', 'key']);
            $table->dropIndex(['user_id', 'group']);
        });

        Schema::table('configurations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete()->after('id');
            $table->unique(['user_id', 'group', 'key']);
            $table->index(['user_id', 'group']);
        });
    }
};
