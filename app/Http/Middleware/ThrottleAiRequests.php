<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class ThrottleAiRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        $userId = (string) $request->user('api')?->getAuthIdentifier();
        $key = "ai-generate:{$userId}";

        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json(['message' => 'Too many AI generation requests.'], 429);
        }

        RateLimiter::hit($key, 60);

        return $next($request);
    }
}
