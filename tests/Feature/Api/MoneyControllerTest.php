<?php

use App\Models\User;
use App\Models\MoneyTransaction;

it('lists transaction months and specific month details', function () {
    $user = User::factory()->create();

    // Create some transactions
    MoneyTransaction::create([
        'user_id' => $user->id,
        'period_serial' => '45000',
        'transacted_at' => '2026-06-15 10:00:00',
        'account' => 'Bank ABC',
        'category' => 'Salary',
        'subcategory' => 'Job 1',
        'description' => 'Monthly income',
        'amount' => 10000000,
        'type' => 'income',
        'currency' => 'IDR',
        'import_hash' => 'hash1',
    ]);

    MoneyTransaction::create([
        'user_id' => $user->id,
        'period_serial' => '45001',
        'transacted_at' => '2026-06-16 12:00:00',
        'account' => 'Cash',
        'category' => 'Food',
        'subcategory' => 'Dinner',
        'description' => 'Restaurant',
        'amount' => 50000,
        'type' => 'expense',
        'currency' => 'IDR',
        'import_hash' => 'hash2',
    ]);

    // Test months summary endpoint
    $this->actingAs($user)
        ->getJson('/api/v1/money/months')
        ->assertOk()
        ->assertJsonPath('months.0.month', '2026-06')
        ->assertJsonPath('months.0.income', 10000000)
        ->assertJsonPath('months.0.expense', 50000)
        ->assertJsonPath('months.0.net', 9950000);

    // Test specific month endpoint
    $this->actingAs($user)
        ->getJson('/api/v1/money/month/2026-06')
        ->assertOk()
        ->assertJsonPath('month', '2026-06')
        ->assertJsonPath('summary.income', 10000000)
        ->assertJsonPath('summary.expense', 50000)
        ->assertJsonCount(2, 'transactions');
});
