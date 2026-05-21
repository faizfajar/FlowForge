<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BroadcastAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_private_tenant_channel_uses_api_guard(): void
    {
        $tenant = Tenant::query()->create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'settings' => []]);
        $user = $this->createUser('admin@example.com', UserRole::ADMIN, $tenant);

        $this->actingAsJwt($user)->postJson('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "private-tenant.{$tenant->id}",
        ])->assertOk()->assertJsonStructure(['auth']);
    }

    public function test_private_tenant_channel_rejects_other_tenant(): void
    {
        $tenantA = Tenant::query()->create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'settings' => []]);
        $tenantB = Tenant::query()->create(['name' => 'Tenant B', 'slug' => 'tenant-b', 'settings' => []]);
        $user = $this->createUser('admin@example.com', UserRole::ADMIN, $tenantA);

        $this->actingAsJwt($user)->postJson('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "private-tenant.{$tenantB->id}",
        ])->assertForbidden();
    }

    private function createUser(string $email, UserRole $role, Tenant $tenant): User
    {
        Role::firstOrCreate(['name' => $role->value, 'guard_name' => 'web']);

        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'User',
            'email' => $email,
            'password' => Hash::make('password123'),
            'role' => $role,
        ]);

        $user->assignRole($role->value);

        return $user;
    }
}
