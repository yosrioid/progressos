<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('money_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('period_serial', 30);
            $table->dateTime('transacted_at');
            $table->string('account', 100)->default('');
            $table->string('category', 150)->default('');
            $table->string('subcategory', 150)->nullable();
            $table->text('description')->nullable();
            $table->decimal('amount', 15, 2);
            $table->enum('type', ['income', 'expense', 'transfer_in', 'transfer_out']);
            $table->string('currency', 10)->default('IDR');
            $table->string('import_hash', 64);
            $table->timestamps();

            $table->unique(['user_id', 'import_hash']);
            $table->index(['user_id', 'transacted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('money_transactions');
    }
};
