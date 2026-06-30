<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configurations', function (Blueprint $table) {
            // Must drop FK before dropping indexes that the FK depends on
            $table->dropForeign(['user_id']);
            $table->dropUnique(['user_id', 'group', 'key']);
            $table->dropIndex(['user_id', 'group']);
            $table->foreignId('user_id')->nullable()->change();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->unique(['user_id', 'group', 'key']);
            $table->index(['user_id', 'group']);
        });

        // All existing configuration records become global (null user_id)
        DB::table('configurations')->update(['user_id' => null]);
    }

    public function down(): void
    {
        Schema::table('configurations', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropUnique(['user_id', 'group', 'key']);
            $table->dropIndex(['user_id', 'group']);
            $table->foreignId('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['user_id', 'group', 'key']);
            $table->index(['user_id', 'group']);
        });
    }
};
