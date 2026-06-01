<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()) {
            return $next($request);
        }

        $plainTextToken = $request->bearerToken();
        if (! $plainTextToken) {
            return $next($request);
        }

        $token = ApiToken::query()
            ->where('token_hash', hash('sha256', $plainTextToken))
            ->whereNull('revoked_at')
            ->with('user')
            ->first();

        if (! $token?->user) {
            return $next($request);
        }

        $token->forceFill(['last_used_at' => now()])->save();
        Auth::setUser($token->user);
        $request->setUserResolver(fn () => $token->user);

        return $next($request);
    }
}
