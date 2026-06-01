<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
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
        return response()->json(['user' => $this->userPayload($request->user())]);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['boolean'],
        ]);

        if (! Auth::guard('web')->attempt($request->only('email', 'password'), (bool) ($credentials['remember'] ?? false))) {
            return response()->json(['message' => 'Invalid credentials.'], 422);
        }

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        return response()->json(['user' => $this->userPayload(Auth::guard('web')->user())]);
    }

    public function register(Request $request)
    {
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

        return response()->json(['user' => $this->userPayload($user)], 201);
    }

    public function updateProfile(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($request->user()->id)],
            'timezone' => ['required', 'timezone'],
            'theme' => ['required', Rule::in(['light', 'dark', 'system'])],
        ]);

        $request->user()->update($data);

        return response()->json(['user' => $this->userPayload($request->user()->fresh())]);
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

        return response()->json(['user' => $this->userPayload($user->fresh())]);
    }

    public function updatePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $request->user()->update(['password' => Hash::make($data['password'])]);

        return response()->json(['ok' => true]);
    }

    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);
        $status = Password::sendResetLink($request->only('email'));

        return $status === Password::RESET_LINK_SENT
            ? response()->json(['message' => __($status)])
            : response()->json(['message' => __($status)], 422);
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
            ? response()->json(['message' => __($status)])
            : response()->json(['message' => __($status)], 422);
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }
        Auth::forgetGuards();

        return response()->json(['ok' => true]);
    }

    private function userPayload(User $user): array
    {
        return $user->toArray() + [
            'avatar_url' => $user->avatar_path ? Storage::disk('public')->url($user->avatar_path) : null,
        ];
    }
}
