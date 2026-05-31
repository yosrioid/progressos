<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('period_type');
            $table->date('period_start');
            $table->date('period_end');
            $table->json('answers')->nullable();
            $table->text('summary')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'period_type', 'period_start']);
        });

        Schema::create('references', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->nullableMorphs('referenceable');
            $table->string('label');
            $table->text('url');
            $table->string('type')->default('link');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'type']);
        });

        Schema::create('saved_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('module');
            $table->string('name');
            $table->json('filters');
            $table->boolean('pinned')->default(false);
            $table->timestamps();
            $table->unique(['user_id', 'module', 'name']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->json('dashboard_layout')->nullable();
            $table->json('notification_preferences')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['dashboard_layout', 'notification_preferences']);
        });
        Schema::dropIfExists('saved_views');
        Schema::dropIfExists('references');
        Schema::dropIfExists('review_entries');
    }
};
