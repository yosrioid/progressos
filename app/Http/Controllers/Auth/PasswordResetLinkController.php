<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Inertia;

class PasswordResetLinkController extends Controller
{
    public function create()
    {
        return Inertia::render('Auth/ForgotPassword');
    }

    public function store(Request $request)
    {
        if (RateLimiter::tooManyAttempts('password-reset|'.$request->ip(), 3)) {
            return back()->with('status', 'Too many reset attempts. Try again shortly.');
        }
        RateLimiter::hit('password-reset|'.$request->ip(), 60);
        $request->validate(['email' => ['required', 'email']]);
        $status = Password::sendResetLink($request->only('email'));

        return back()->with('status', __($status));
    }
}
