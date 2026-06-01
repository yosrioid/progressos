<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider')->default('google_sheets');
            $table->string('name')->default('Google Sheets');
            $table->string('spreadsheet_id')->nullable();
            $table->text('credentials')->nullable();
            $table->string('status')->default('draft');
            $table->timestamp('last_verified_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'provider']);
        });

        Schema::create('backup_syncs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('backup_connection_id')->nullable()->constrained()->nullOnDelete();
            $table->string('module');
            $table->string('frequency');
            $table->string('destination_sheet_name');
            $table->boolean('enabled')->default(true);
            $table->json('filters')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'enabled', 'next_run_at']);
            $table->index(['user_id', 'module']);
        });

        Schema::create('backup_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('backup_sync_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('queued');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('rows_exported')->default(0);
            $table->string('file_path')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
            $table->index(['backup_sync_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_runs');
        Schema::dropIfExists('backup_syncs');
        Schema::dropIfExists('backup_connections');
    }
};
