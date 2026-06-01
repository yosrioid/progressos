<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use Illuminate\Http\Request;

class ApiTokenController extends Controller
{
    public function index(Request $request)
    {
        return response()->json([
            'tokens' => $request->user()->apiTokens()
                ->latest()
                ->get(['id', 'name', 'abilities', 'last_used_at', 'revoked_at', 'created_at']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'abilities' => ['nullable', 'array'],
            'abilities.*' => ['string', 'max:80'],
        ]);

        [$token, $plainTextToken] = ApiToken::issue($request->user(), $data['name'], $data['abilities'] ?? ['*']);

        return response()->json([
            'token' => $token->only(['id', 'name', 'abilities', 'created_at']),
            'plain_text_token' => $plainTextToken,
        ], 201);
    }

    public function destroy(Request $request, ApiToken $token)
    {
        abort_unless($token->user_id === $request->user()->id, 403);
        $token->forceFill(['revoked_at' => now()])->save();

        return response()->noContent();
    }
}
