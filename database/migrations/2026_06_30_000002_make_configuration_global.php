<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $indexes = collect(Schema::getIndexes('configurations'))->pluck('name')->toArray();
        $fks = collect(Schema::getForeignKeys('configurations'))->pluck('name')->toArray();

        // Step 1: drop FK first (MySQL requires FK gone before touching indexes it uses)
        Schema::table('configurations', function (Blueprint $table) use ($fks) {
            if (in_array('configurations_user_id_foreign', $fks)) {
                $table->dropForeign(['user_id']);
            }
        });

        // Step 2: drop indexes only if they exist
        Schema::table('configurations', function (Blueprint $table) use ($indexes) {
            if (in_array('configurations_user_id_group_key_unique', $indexes)) {
                $table->dropUnique('configurations_user_id_group_key_unique');
            }
            if (in_array('configurations_user_id_group_index', $indexes)) {
                $table->dropIndex('configurations_user_id_group_index');
            }
        });

        // Step 3: make user_id nullable, re-add FK + indexes
        Schema::table('configurations', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->unique(['user_id', 'group', 'key']);
            $table->index(['user_id', 'group']);
        });

        DB::table('configurations')->update(['user_id' => null]);
    }

    public function down(): void
    {
        $fks = collect(Schema::getForeignKeys('configurations'))->pluck('name')->toArray();

        Schema::table('configurations', function (Blueprint $table) use ($fks) {
            if (in_array('configurations_user_id_foreign', $fks)) {
                $table->dropForeign(['user_id']);
            }
        });

        Schema::table('configurations', function (Blueprint $table) {
            $table->dropUnique('configurations_user_id_group_key_unique');
            $table->dropIndex('configurations_user_id_group_index');
            $table->foreignId('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['user_id', 'group', 'key']);
            $table->index(['user_id', 'group']);
        });
    }
};
