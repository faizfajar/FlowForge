<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class AuthService
{
    /**
     * @param  array{name: string, email: string, password: string, tenant_name: string}  $data
     * @return array{user: User, token: string}
     */
    public function register(array $data): array
    {
        $tenant = Tenant::query()->create([
            'name' => $data['tenant_name'],
            'slug' => Str::slug($data['tenant_name']).'-'.Str::lower(Str::random(6)),
            'settings' => [],
        ]);

        Role::firstOrCreate(['name' => UserRole::ADMIN->value, 'guard_name' => 'web']);

        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => UserRole::ADMIN,
        ]);

        $user->assignRole(UserRole::ADMIN->value);

        return [
            'user' => $user->load('tenant'),
            'token' => $user->createToken('api')->plainTextToken,
        ];
    }

    /**
     * @param  array{email: string, password: string}  $credentials
     * @return array{user: User, token: string}
     */
    public function login(array $credentials): array
    {
        if (! Auth::attempt($credentials)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are invalid.'],
            ])->status(401);
        }

        /** @var User $user */
        $user = Auth::user();

        return [
            'user' => $user->load('tenant'),
            'token' => $user->createToken('api')->plainTextToken,
        ];
    }

    public function logout(User $user): void
    {
        $token = $user->currentAccessToken();

        if ($token !== null) {
            $token->delete();
        }
    }
}
