<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

trait Auditable
{
    protected static function bootAuditable(): void
    {
        foreach (['created', 'updated', 'deleted'] as $event) {
            static::$event(function (Model $model) use ($event) {
                AuditLog::create([
                    'user_id' => Auth::id() ?: $model->getAttribute('user_id'),
                    'event' => class_basename($model).'.'.$event,
                    'auditable_type' => $model::class,
                    'auditable_id' => $model->getKey(),
                    'ip_address' => request()?->ip(),
                    'user_agent' => request()?->userAgent(),
                    'metadata' => $event === 'updated' ? ['changes' => $model->getChanges()] : null,
                ]);
            });
        }
    }
}
