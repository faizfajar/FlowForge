<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\Response;

class ThrottleByTenant
{
    private const LIMIT = 100;

    private const WINDOW_SECONDS = 900;

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || $user->tenant_id === null || app()->environment('testing')) {
            return $next($request);
        }

        $key = "throttle:tenant:{$user->tenant_id}";
        $count = (int) Redis::incr($key);

        if ($count === 1) {
            Redis::expire($key, self::WINDOW_SECONDS);
        }

        if ($count > self::LIMIT) {
            $ttl = max(1, (int) Redis::ttl($key));

            return new JsonResponse(
                ['message' => 'Too many requests.'],
                Response::HTTP_TOO_MANY_REQUESTS,
                ['Retry-After' => (string) $ttl]
            );
        }

        return $next($request);
    }
}
