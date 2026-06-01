<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    public function __invoke(Request $request)
    {
        $logs = $request->user()->auditLogs()
            ->latest()
            ->paginate((int) min(max((int) $request->query('per_page', 20), 10), 50));

        $logs->getCollection()->transform(fn (AuditLog $log) => [
            'id' => $log->id,
            'event' => $log->event,
            'label' => str($log->event)->replace('.', ' ')->headline()->toString(),
            'record_type' => class_basename((string) $log->auditable_type),
            'record_id' => $log->auditable_id,
            'metadata' => $log->metadata,
            'created_at' => $log->created_at,
        ]);

        return ApiResponse::paginated('activity', $logs);
    }
}
