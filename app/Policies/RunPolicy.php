<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\WorkflowRun;

class RunPolicy
{
    public function view(User $user, WorkflowRun $run): bool
    {
        return $user->tenant_id === $run->tenant_id;
    }

    public function cancel(User $user, WorkflowRun $run): bool
    {
        return $user->tenant_id === $run->tenant_id
            && ($user->role === UserRole::ADMIN || $run->triggered_by === $user->id);
    }
}
