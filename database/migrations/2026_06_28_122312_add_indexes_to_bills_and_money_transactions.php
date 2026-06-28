<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            $table->index(['user_id', 'is_active'], 'bills_user_active_idx');
        });

        Schema::table('bill_payments', function (Blueprint $table) {
            $table->index(['user_id', 'month'], 'bill_payments_user_month_idx');
        });

        Schema::table('money_transactions', function (Blueprint $table) {
            $table->index(['user_id', 'transacted_at'], 'money_transactions_user_date_idx');
        });
    }

    public function down(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            $table->dropIndex('bills_user_active_idx');
        });

        Schema::table('bill_payments', function (Blueprint $table) {
            $table->dropIndex('bill_payments_user_month_idx');
        });

        Schema::table('money_transactions', function (Blueprint $table) {
            $table->dropIndex('money_transactions_user_date_idx');
        });
    }
};
