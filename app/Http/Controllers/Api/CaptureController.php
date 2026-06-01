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
        return ApiResponse::item('record', $capture->capture($request->user(), $request->validated()), 201, 'Captured.');
    }
}
