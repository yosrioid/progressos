<?php

use App\Http\Controllers\Api\ApiTokenController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'ability:tokens', 'throttle:api-tokens'])->group(function () {
    Route::apiResource('tokens', ApiTokenController::class)->only(['index', 'store', 'destroy']);
});
