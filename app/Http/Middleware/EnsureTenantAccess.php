<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantAccess
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->tenant_id !== null) {
            app()->instance('tenant.id', $user->tenant_id);
            $request->attributes->set('tenant_id', $user->tenant_id);
        }

        return $next($request);
    }
}
