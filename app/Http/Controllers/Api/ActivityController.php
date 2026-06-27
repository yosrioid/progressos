<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    public function __invoke(Request $request)
    {
        $query = $request->user()->auditLogs()->latest();

        if ($type = $request->query('type')) {
            $map = [
                'WorkLog' => 'App\\Models\\WorkLog',
                'Task' => 'App\\Models\\Task',
                'LearningEntry' => 'App\\Models\\LearningEntry',
                'DailyProgress' => 'App\\Models\\DailyProgress',
                'Milestone' => 'App\\Models\\Milestone',
                'Doc' => 'App\\Models\\Doc',
            ];
            if (isset($map[$type])) {
                $query->where('auditable_type', $map[$type]);
            }
        }

        if ($from = $request->query('from')) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $request->query('to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        $logs = $query->paginate((int) min(max((int) $request->query('per_page', 20), 10), 50));

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

    public function summary(Request $request): JsonResponse
    {
        $counts = $request->user()->auditLogs()
            ->selectRaw('auditable_type, COUNT(*) as count')
            ->when($request->query('from'), fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($request->query('to'), fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->groupBy('auditable_type')
            ->pluck('count', 'auditable_type')
            ->mapWithKeys(fn ($count, $type) => [class_basename($type) => $count]);

        return ApiResponse::item('summary', $counts);
    }
}
