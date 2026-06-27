<?php

use App\Models\Bill;
use App\Models\BillPayment;
use App\Models\User;

it('loads the previous month payment for the selected bill month', function () {
    $user = User::factory()->create();
    $bill = Bill::create([
        'user_id' => $user->id,
        'name' => 'Internet',
        'estimated_amount' => 350000,
        'due_day' => 10,
        'category' => 'Utilities',
        'is_active' => true,
        'is_recurring' => true,
    ]);

    BillPayment::create([
        'user_id' => $user->id,
        'bill_id' => $bill->id,
        'month' => '2026-05',
        'actual_amount' => 345000,
        'paid_at' => now(),
        'skipped' => false,
    ]);

    $this->actingAs($user)
        ->getJson('/api/v1/bills/month/2026-06')
        ->assertOk()
        ->assertJsonPath('month', '2026-06')
        ->assertJsonPath('bills.0.name', 'Internet')
        ->assertJsonPath('bills.0.last_payment.month', '2026-05')
        ->assertJsonPath('bills.0.last_payment.actual_amount', '345000.00');
});
