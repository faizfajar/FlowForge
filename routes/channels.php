<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\WorkflowDefinition;

Broadcast::channel('App.Models.User.{id}', function ($user, string $id): bool {
    return (string) $user->id === $id;
}, ['guards' => ['api']]);

Broadcast::channel('tenant.{tenantId}', function ($user, string $tenantId): bool {
    return (string) $user->tenant_id === $tenantId;
}, ['guards' => ['api']]);

Broadcast::channel('tenant.{tenantId}.workflows', function ($user, string $tenantId): bool {
    return (string) $user->tenant_id === $tenantId;
}, ['guards' => ['api']]);

Broadcast::channel('tenant.{tenantId}.workflow.{workflowId}', function ($user, string $tenantId, string $workflowId): bool {
    return (string) $user->tenant_id === $tenantId
        && WorkflowDefinition::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($workflowId)
            ->exists();
}, ['guards' => ['api']]);
