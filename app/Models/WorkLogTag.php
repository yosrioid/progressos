<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class WorkLogTag extends Model
{
    protected $fillable = ['user_id', 'name'];

    public function workLogs(): BelongsToMany
    {
        return $this->belongsToMany(WorkLog::class, 'work_log_tag');
    }
}
