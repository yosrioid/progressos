<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            $table->boolean('is_recurring')->default(true)->after('is_active');
            $table->string('month', 7)->nullable()->after('is_recurring');
        });

        Schema::table('bill_payments', function (Blueprint $table) {
            $table->boolean('skipped')->default(false)->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            $table->dropColumn(['is_recurring', 'month']);
        });

        Schema::table('bill_payments', function (Blueprint $table) {
            $table->dropColumn('skipped');
        });
    }
};
