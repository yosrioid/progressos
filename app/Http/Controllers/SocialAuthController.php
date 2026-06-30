<?php

namespace App\Http\Controllers;

use App\Models\Configuration;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    public function googleRedirect()
    {
        $this->applyGoogleConfig();

        return Socialite::driver('google')->redirect();
    }

    public function googleConnect(Request $request)
    {
        // Explicit connect from Profile — store logged-in user id in session
        $request->session()->put('google_connect_user_id', $request->user()->id);

        $this->applyGoogleConfig();

        return Socialite::driver('google')->redirect();
    }

    public function googleCallback(Request $request)
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

        // Explicit connect from Profile
        if ($connectUserId = $request->session()->pull('google_connect_user_id')) {
            $user = User::find($connectUserId);
            if ($user) {
                $user->update(['google_id' => $googleUser->getId(), 'google_connected' => true]);
                Auth::guard('web')->login($user);

                return redirect('/profile?google=connected');
            }
        }

        // Login flow: find by google_id first
        $user = User::where('google_id', $googleUser->getId())->first();

        // Auto-link only if never explicitly managed (google_connected IS NULL)
        if (! $user) {
            $user = User::where('email', $googleUser->getEmail())->whereNull('google_connected')->first();
        }

        if (! $user) {
            return redirect('/login?error=no_account');
        }

        if (! $user->google_id) {
            $user->update(['google_id' => $googleUser->getId(), 'google_connected' => true]);
        }

        Auth::guard('web')->login($user, remember: true);
        $request->session()->regenerate();

        return redirect('/dashboard');
    }

    private function applyGoogleConfig(): void
    {
        $config = Configuration::getValue(null, 'auth', 'google_oauth');
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
