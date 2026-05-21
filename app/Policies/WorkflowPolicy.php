<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\WorkflowDefinition;

class WorkflowPolicy
{
    public function view(User $user, WorkflowDefinition $workflow): bool
    {
        return $user->tenant_id === $workflow->tenant_id;
    }

    public function create(User $user): bool
    {
        return in_array($user->role, [UserRole::ADMIN, UserRole::EDITOR], true);
    }

    public function update(User $user, WorkflowDefinition $workflow): bool
    {
        return $user->tenant_id === $workflow->tenant_id
            && in_array($user->role, [UserRole::ADMIN, UserRole::EDITOR], true);
    }

    public function delete(User $user, WorkflowDefinition $workflow): bool
    {
        return $user->tenant_id === $workflow->tenant_id && $user->role === UserRole::ADMIN;
    }

    public function trigger(User $user, WorkflowDefinition $workflow): bool
    {
        return $user->tenant_id === $workflow->tenant_id
            && in_array($user->role, [UserRole::ADMIN, UserRole::EDITOR], true);
    }
}
