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
        session(['google_oauth_intent' => 'login']);
        $this->applyGoogleConfig();

        return Socialite::driver('google')->redirect();
    }

    public function googleLink()
    {
        session(['google_oauth_intent' => 'link']);
        $this->applyGoogleConfig();

        return Socialite::driver('google')->redirect();
    }

    public function googleCallback()
    {
        $this->applyGoogleConfig();
        $intent = session()->pull('google_oauth_intent', 'login');

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Throwable) {
            return $intent === 'link'
                ? redirect('/profile?error=google_failed')
                : redirect('/login?error=google_failed');
        }

        return $intent === 'link'
            ? $this->handleLink($googleUser)
            : $this->handleLogin($googleUser);
    }

    private function handleLogin(mixed $googleUser): mixed
    {
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

    private function handleLink(mixed $googleUser): mixed
    {
        $user = Auth::guard('web')->user();

        if (! $user) {
            return redirect('/login');
        }

        $taken = User::where('google_id', $googleUser->getId())
            ->where('id', '!=', $user->id)
            ->exists();

        if ($taken) {
            return redirect('/profile?error=google_taken');
        }

        $user->update(['google_id' => $googleUser->getId()]);

        return redirect('/profile?success=google_linked');
    }

    private function applyGoogleConfig(): void
    {
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
