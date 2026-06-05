<?php

use App\Http\Controllers\SocialAuthController;
use Illuminate\Support\Facades\Route;

// Login via Google — guest only
Route::middleware('guest')->get('/auth/google', [SocialAuthController::class, 'googleRedirect'])->name('auth.google');

// Callback handles both login flow (guest) and link flow (authenticated)
Route::get('/auth/google/callback', [SocialAuthController::class, 'googleCallback'])->name('auth.google.callback');

// Link Google to existing authenticated account
Route::middleware('auth')->get('/profile/google/link', [SocialAuthController::class, 'googleLink'])->name('profile.google.link');

Route::view('/{any?}', 'app')->where('any', '.*');
