<?php

use App\Http\Controllers\SocialAuthController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/auth/google', [SocialAuthController::class, 'googleRedirect'])->name('auth.google');
    Route::get('/auth/google/callback', [SocialAuthController::class, 'googleCallback'])->name('auth.google.callback');
});

Route::view('/{any?}', 'app')->where('any', '.*');
