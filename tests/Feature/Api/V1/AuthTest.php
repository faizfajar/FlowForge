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

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_creates_tenant_and_admin_user_and_returns_token(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'tenant_name' => 'Acme Corp',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.user.email', 'admin@example.com')
            ->assertJsonPath('data.user.role', 'admin')
            ->assertJsonStructure(['data' => ['token', 'refresh_token', 'token_type', 'expires_in']]);

        $this->assertDatabaseHas('tenants', ['name' => 'Acme Corp']);
        $this->assertDatabaseHas('users', ['email' => 'admin@example.com', 'role' => 'admin']);
    }

    public function test_login_with_valid_credentials_returns_token(): void
    {
        $this->createUser('admin@example.com', UserRole::ADMIN);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'password123',
        ])->assertOk()->assertJsonStructure(['data' => ['token', 'refresh_token', 'token_type', 'expires_in']]);
    }

    public function test_refresh_returns_new_jwt_token_pair(): void
    {
        $this->createUser('admin@example.com', UserRole::ADMIN);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'password123',
        ])->assertOk();

        $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => $login->json('data.refresh_token'),
        ])->assertOk()->assertJsonStructure(['data' => ['token', 'refresh_token', 'token_type', 'expires_in']]);
    }

    public function test_login_with_invalid_credentials_returns_401(): void
    {
        $this->createUser('admin@example.com', UserRole::ADMIN);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(401);
    }

    public function test_protected_route_without_token_returns_401(): void
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_logout_accepts_valid_jwt_token(): void
    {
        $user = $this->createUser('admin@example.com', UserRole::ADMIN);

        $this->actingAsJwt($user)->postJson('/api/v1/auth/logout')->assertNoContent();
    }

    private function createUser(string $email, UserRole $role): User
    {
        Role::firstOrCreate(['name' => $role->value, 'guard_name' => 'web']);
        $tenant = Tenant::query()->create(['name' => 'Tenant', 'slug' => 'tenant', 'settings' => []]);
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
