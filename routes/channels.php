<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, string $id): bool {
    return (string) $user->id === $id;
}, ['guards' => ['api']]);

Broadcast::channel('tenant.{tenantId}', function ($user, string $tenantId): bool {
    return (string) $user->tenant_id === $tenantId;
}, ['guards' => ['api']]);
