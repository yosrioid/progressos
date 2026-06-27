<?php

use App\Http\Controllers\Api\DocShareController;
use Illuminate\Support\Facades\Route;

require __DIR__.'/api/auth.php';
require __DIR__.'/api/tokens.php';
require __DIR__.'/api/v1.php';

Route::prefix('share')->group(function () {
    Route::get('docs/{token}', [DocShareController::class, 'show']);
    Route::get('docs/{token}/files/{docFile}', [DocShareController::class, 'file']);
});
