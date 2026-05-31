<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class DailyProgressTag extends Model
{
    protected $fillable = ['user_id', 'name'];

    public function entries(): BelongsToMany
    {
        return $this->belongsToMany(DailyProgressEntry::class, 'daily_progress_entry_tag');
    }
}
