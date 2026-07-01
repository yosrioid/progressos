<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Configuration;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;

class AuthController extends Controller
{
    public function me(Request $request)
    {
        return ApiResponse::item('user', $this->userPayload($request->user()));
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['boolean'],
        ]);

        if (! Auth::guard('web')->attempt($request->only('email', 'password'), (bool) ($credentials['remember'] ?? false))) {
            return ApiResponse::ok([], 'Invalid credentials.', 422);
        }

        if (Auth::guard('web')->user()->isDisabled()) {
            Auth::guard('web')->logout();

            return ApiResponse::ok([], 'Account is disabled. Please contact the administrator.', 403);
        }

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        return ApiResponse::item('user', $this->userPayload(Auth::guard('web')->user()), message: 'Logged in.');
    }

    public function register(Request $request)
    {
        if (! config('app.registration_enabled', false)) {
            return ApiResponse::ok(['message' => 'Registration is disabled.'], 'Registration is disabled.', 403);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
            'timezone' => ['nullable', 'timezone'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'timezone' => $data['timezone'] ?? config('app.timezone'),
            'theme' => 'system',
        ]);

        Auth::guard('web')->login($user);
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        return ApiResponse::item('user', $this->userPayload($user), 201, 'Registered.');
    }

    public function updateProfile(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($request->user()->id)],
            'timezone' => ['required', 'timezone'],
        ]);

        $request->user()->update($data);

        return ApiResponse::item('user', $this->userPayload($request->user()->fresh()), message: 'Profile updated.');
    }

    public function updateAvatar(Request $request)
    {
        $data = $request->validate([
            'avatar' => ['required', 'image', 'max:2048'],
        ]);

        $user = $request->user();
        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        $path = $data['avatar']->store('avatars', 'public');
        $user->update(['avatar_path' => $path]);

        return ApiResponse::item('user', $this->userPayload($user->fresh()), message: 'Avatar updated.');
    }

    public function updatePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $request->user()->update(['password' => Hash::make($data['password'])]);

        return ApiResponse::ok(['ok' => true], 'Password updated.');
    }

    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);
        $this->applyMailConfig($request->string('email')->toString());
        Password::sendResetLink($request->only('email'));

        // Always return 200 with a generic message — prevents email enumeration
        return ApiResponse::ok([], __('passwords.sent'));
    }

    private function applyMailConfig(string $email): void
    {
        if (! User::where('email', $email)->exists()) {
            return;
        }

        $config = Configuration::getValue(null, 'mail', 'smtp');
        if (! is_array($config) || ($config['mailer'] ?? 'log') === 'log') {
            return;
        }

        if ($config['mailer'] === 'resend' && filled($config['api_key'] ?? null)) {
            config([
                'mail.default' => 'resend',
                'services.resend.key' => $config['api_key'],
            ]);
        } elseif ($config['mailer'] === 'smtp' && filled($config['host'] ?? null)) {
            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp.host' => $config['host'],
                'mail.mailers.smtp.port' => $config['port'] ?? 587,
                'mail.mailers.smtp.username' => $config['username'] ?? '',
                'mail.mailers.smtp.password' => $config['password'] ?? '',
            ]);
        }

        if (filled($config['from_address'] ?? null)) {
            config([
                'mail.from.address' => $config['from_address'],
                'mail.from.name' => $config['from_name'] ?? config('app.name'),
            ]);
        }
    }

    public function resetPassword(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::reset($data, function (User $user, string $password) {
            $user->forceFill([
                'password' => Hash::make($password),
                'remember_token' => Str::random(60),
            ])->save();
        });

        return $status === Password::PASSWORD_RESET
            ? ApiResponse::ok([], __($status))
            : ApiResponse::ok([], __($status), 422);
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }
        Auth::forgetGuards();

        return ApiResponse::ok(['ok' => true], 'Logged out.');
    }

    public function disconnectGoogle(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->google_id) {
            return ApiResponse::ok([], 'Google not linked.', 422);
        }

        if (! $user->password) {
            return ApiResponse::ok([], 'Set a password first before disconnecting Google.', 422);
        }

        $user->update(['google_id' => null, 'google_connected' => false]);

        return ApiResponse::item('user', $this->userPayload($user->fresh()));
    }

    private function userPayload(User $user): array
    {
        $googleConfig = Configuration::getValue(null, 'auth', 'google_oauth', []);

        return $user->toArray() + [
            'avatar_url' => $user->avatar_path ? '/storage/'.$user->avatar_path : null,
            'is_admin' => $user->isAdmin(),
            'has_google' => (bool) $user->google_connected,
            'google_sso_enabled' => (bool) (is_array($googleConfig) ? ($googleConfig['enabled'] ?? false) : false),
        ];
    }
}
