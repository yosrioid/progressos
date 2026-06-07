<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\QuickCaptureRequest;
use App\Services\QuickCaptureService;
use App\Support\ApiResponse;

class CaptureController extends Controller
{
    public function __invoke(QuickCaptureRequest $request, QuickCaptureService $capture)
    {
        $data = $request->validated();
        $record = $capture->captureIdempotently($request->user(), $data, $request->header('Idempotency-Key'));

        $pathMap = ['task' => 'tasks', 'blocker' => 'tasks', 'work_log' => 'work-logs', 'daily_progress' => 'daily-progress', 'learning' => 'learning'];
        $path = ($pathMap[$data['type']] ?? null) ? "/{$pathMap[$data['type']]}/{$record->getKey()}" : null;

        return ApiResponse::item('record', $record, 201, 'Captured.', ['record_path' => $path]);
    }
}
