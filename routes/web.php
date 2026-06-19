<?php

use App\Http\Controllers\SocialAuthController;
use Illuminate\Support\Facades\Route;
use Symfony\Component\Yaml\Yaml;

Route::middleware('guest')->group(function () {
    Route::get('/auth/google', [SocialAuthController::class, 'googleRedirect'])->name('auth.google');
    Route::get('/auth/google/callback', [SocialAuthController::class, 'googleCallback'])->name('auth.google.callback');
});

Route::get('/api-docs', function () {
    $spec = json_encode(Yaml::parseFile(base_path('docs/openapi.yaml')), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    return view('api-docs', ['spec' => $spec]);
});
Route::get('/api-docs/openapi.yaml', function () {
    return response()->file(base_path('docs/openapi.yaml'), ['Content-Type' => 'application/x-yaml']);
});

Route::view('/{any?}', 'app')->where('any', '.*');
