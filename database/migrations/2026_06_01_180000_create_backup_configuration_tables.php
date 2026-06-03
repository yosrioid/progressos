<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configurations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('group');
            $table->string('key');
            $table->json('value')->nullable();
            $table->text('encrypted_value')->nullable();
            $table->string('type')->default('array');
            $table->timestamps();
            $table->unique(['user_id', 'group', 'key']);
            $table->index(['user_id', 'group']);
        });

        Schema::create('backup_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('sync_id')->nullable();
            $table->string('module')->nullable();
            $table->string('destination_sheet_name')->nullable();
            $table->string('status')->default('queued');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('rows_exported')->default(0);
            $table->string('file_path')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
            $table->index(['user_id', 'sync_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_runs');
        Schema::dropIfExists('configurations');
    }
};
