<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillPayment extends Model
{
    protected $fillable = ['bill_id', 'user_id', 'month', 'actual_amount', 'notes', 'paid_at', 'skipped'];

    protected $casts = [
        'actual_amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'skipped' => 'boolean',
    ];

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }
}
