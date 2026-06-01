<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\PersonalAccessToken;

class ApiTokenController extends Controller
{
    public function index(Request $request)
    {
        return response()->json([
            'tokens' => $request->user()->tokens()
                ->latest()
                ->get(['id', 'name', 'abilities', 'last_used_at', 'expires_at', 'created_at']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'abilities' => ['nullable', 'array'],
            'abilities.*' => ['string', 'max:80'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);

        $newAccessToken = $request->user()->createToken(
            $data['name'],
            $data['abilities'] ?? ['*'],
            isset($data['expires_at']) ? Carbon::parse($data['expires_at']) : null,
        );

        $token = $newAccessToken->accessToken;

        return response()->json([
            'token' => $token->only(['id', 'name', 'abilities', 'expires_at', 'created_at']),
            'plain_text_token' => $newAccessToken->plainTextToken,
        ], 201);
    }

    public function destroy(Request $request, PersonalAccessToken $token)
    {
        abort_unless(
            $token->tokenable_id === $request->user()->id
                && $token->tokenable_type === $request->user()::class,
            403,
        );

        $token->delete();

        return response()->noContent();
    }
}
