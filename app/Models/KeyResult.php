<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KeyResult extends Model
{
    protected $fillable = [
        'goal_id', 'user_id', 'title', 'metric_type',
        'current_value', 'target_value', 'unit', 'notes', 'status', 'order',
    ];

    protected function casts(): array
    {
        return [
            'current_value' => 'float',
            'target_value' => 'float',
        ];
    }

    public function goal(): BelongsTo
    {
        return $this->belongsTo(Goal::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function progressPercent(): float
    {
        if ($this->metric_type === 'boolean') {
            return $this->current_value >= 1 ? 100 : 0;
        }

        return $this->target_value > 0
            ? min(100, round(($this->current_value / $this->target_value) * 100, 1))
            : 0;
    }
}
