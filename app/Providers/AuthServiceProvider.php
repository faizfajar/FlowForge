<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\WorkflowDefinition;
use App\Models\WorkflowRun;
use App\Policies\RunPolicy;
use App\Policies\WorkflowPolicy;
use App\Services\Auth\JwtService;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Auth;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    protected $policies = [
        WorkflowDefinition::class => WorkflowPolicy::class,
        WorkflowRun::class => RunPolicy::class,
    ];

    public function boot(): void
    {
        Auth::viaRequest('jwt', function ($request) {
            return app(JwtService::class)->userFromToken($request->bearerToken());
        });
    }
}
