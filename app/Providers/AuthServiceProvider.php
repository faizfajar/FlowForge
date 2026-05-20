<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\WorkflowDefinition;
use App\Models\WorkflowRun;
use App\Policies\RunPolicy;
use App\Policies\WorkflowPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    protected $policies = [
        WorkflowDefinition::class => WorkflowPolicy::class,
        WorkflowRun::class => RunPolicy::class,
    ];
}
