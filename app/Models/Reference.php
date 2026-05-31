<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Reference extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = ['user_id', 'referenceable_type', 'referenceable_id', 'label', 'url', 'type', 'notes'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function referenceable(): MorphTo
    {
        return $this->morphTo();
    }
}
