<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApiTokenRequest;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\PersonalAccessToken;

class ApiTokenController extends Controller
{
    public function index(Request $request)
    {
        return ApiResponse::collection(
            'tokens',
            $request->user()->tokens()
                ->latest()
                ->get(['id', 'name', 'abilities', 'last_used_at', 'expires_at', 'created_at'])
        );
    }

    public function store(ApiTokenRequest $request)
    {
        $data = $request->validated();

        $newAccessToken = $request->user()->createToken(
            $data['name'],
            $data['abilities'] ?? ['read', 'write', 'capture', 'reports'],
            isset($data['expires_at']) ? Carbon::parse($data['expires_at']) : null,
        );

        $token = $newAccessToken->accessToken;

        return ApiResponse::item('token', $token->only(['id', 'name', 'abilities', 'expires_at', 'created_at']), 201, 'API token created.', [
            'plain_text_token' => $newAccessToken->plainTextToken,
        ]);
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
