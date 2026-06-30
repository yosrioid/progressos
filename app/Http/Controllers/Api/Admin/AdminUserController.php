<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AdminUserController extends Controller
{
    public function index(): JsonResponse
    {
        $users = User::where('role', 'user')
            ->orderBy('name')
            ->get()
            ->map(fn (User $u) => $this->userPayload($u));

        return ApiResponse::collection('users', $users);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'timezone' => ['nullable', 'timezone'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'timezone' => $data['timezone'] ?? 'Asia/Jakarta',
            'theme' => 'system',
            'role' => 'user',
            'email_verified_at' => now(),
        ]);

        return ApiResponse::item('user', $this->userPayload($user), 201, 'User created.');
    }

    public function update(Request $request, User $user): JsonResponse
    {
        abort_if($user->isAdmin(), 403, 'Cannot edit admin accounts.');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', \Illuminate\Validation\Rule::unique('users', 'email')->ignore($user->id)],
            'timezone' => ['nullable', 'timezone'],
        ]);

        $user->update($data);

        return ApiResponse::item('user', $this->userPayload($user->fresh()), message: 'User updated.');
    }

    public function resetPassword(Request $request, User $user): JsonResponse
    {
        abort_if($user->isAdmin(), 403, 'Cannot reset admin password.');

        $data = $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user->update(['password' => Hash::make($data['password'])]);

        return ApiResponse::ok(['ok' => true], 'Password reset.');
    }

    public function disable(User $user): JsonResponse
    {
        abort_if($user->isAdmin(), 403, 'Cannot disable admin accounts.');
        abort_if($user->isDisabled(), 422, 'User is already disabled.');

        $user->update(['disabled_at' => now()]);

        return ApiResponse::item('user', $this->userPayload($user->fresh()), message: 'User disabled.');
    }

    public function enable(User $user): JsonResponse
    {
        abort_if($user->isAdmin(), 403, 'Cannot modify admin accounts.');
        abort_unless($user->isDisabled(), 422, 'User is already active.');

        $user->update(['disabled_at' => null]);

        return ApiResponse::item('user', $this->userPayload($user->fresh()), message: 'User enabled.');
    }

    public function destroy(User $user): JsonResponse
    {
        abort_if($user->isAdmin(), 403, 'Cannot delete admin accounts.');

        $user->delete();

        return ApiResponse::ok(['ok' => true], 'User deleted.');
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'timezone' => $user->timezone,
            'disabled_at' => $user->disabled_at?->toIso8601String(),
            'is_disabled' => $user->isDisabled(),
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }
}
