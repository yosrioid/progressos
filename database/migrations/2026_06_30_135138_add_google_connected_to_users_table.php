<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('google_connected')->nullable()->default(null)->after('google_id');
        });

        // Mark existing linked accounts as connected
        DB::table('users')->whereNotNull('google_id')->update(['google_connected' => true]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('google_connected');
        });
    }
};
