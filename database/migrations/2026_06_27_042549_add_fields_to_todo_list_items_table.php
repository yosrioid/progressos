<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('todo_list_items', function (Blueprint $table) {
            $table->tinyInteger('priority')->default(0)->after('completed'); // 0=none 1=high 2=medium 3=low
            $table->date('due_date')->nullable()->after('priority');
            $table->text('notes')->nullable()->after('due_date');
        });
    }

    public function down(): void
    {
        Schema::table('todo_list_items', function (Blueprint $table) {
            $table->dropColumn(['priority', 'due_date', 'notes']);
        });
    }
};
