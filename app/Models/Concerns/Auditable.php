<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

trait Auditable
{
    protected static function bootAuditable(): void
    {
        static::created(function (Model $model) {
            self::logAudit($model, 'created');
        });
        static::updated(function (Model $model) {
            self::logAudit($model, 'updated');
        });
        static::deleted(function (Model $model) {
            self::logAudit($model, 'deleted');
        });
    }

    private static function logAudit(Model $model, string $event): void
    {
        $metadata = match ($event) {
            'updated' => [
                'before' => collect($model->getChanges())->except('updated_at')->keys()
                    ->mapWithKeys(fn ($key) => [$key => $model->getOriginal($key)])
                    ->all(),
                'after' => collect($model->getChanges())->except('updated_at')->all(),
            ],
            'deleted' => ['snapshot' => $model->attributesToArray()],
            default => null,
        };

        AuditLog::create([
            'user_id' => Auth::id() ?: $model->getAttribute('user_id'),
            'event' => class_basename($model).'.'.$event,
            'auditable_type' => $model::class,
            'auditable_id' => $model->getKey(),
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'metadata' => $metadata,
        ]);
    }
}
