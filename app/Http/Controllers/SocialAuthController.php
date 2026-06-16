<?php

namespace App\Http\Controllers;

use App\Models\Configuration;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    public function googleRedirect()
    {
        $this->applyGoogleConfig();

        return Socialite::driver('google')->redirect();
    }

    public function googleCallback()
    {
        $this->applyGoogleConfig();

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Throwable) {
            return redirect('/login?error=google_failed');
        }

        if (! ($googleUser->user['email_verified'] ?? false)) {
            return redirect('/login?error=google_unverified');
        }

        $user = User::where('google_id', $googleUser->getId())->first()
            ?? User::where('email', $googleUser->getEmail())->first();

        if (! $user) {
            return redirect('/login?error=no_account');
        }

        if (! $user->google_id) {
            $user->update(['google_id' => $googleUser->getId()]);
        }

        Auth::guard('web')->login($user, remember: true);
        request()->session()->regenerate();

        return redirect('/dashboard');
    }

    private function applyGoogleConfig(): void
    {
        // Single-user app: read Google OAuth credentials from the first user's configuration.
        $user = User::first();
        if (! $user) {
            return;
        }

        $config = Configuration::getValue($user, 'auth', 'google_oauth');
        if (! is_array($config)) {
            return;
        }

        if (filled($config['client_id'] ?? null)) {
            config(['services.google.client_id' => $config['client_id']]);
        }
        if (filled($config['client_secret'] ?? null)) {
            config(['services.google.client_secret' => $config['client_secret']]);
        }
    }
}
