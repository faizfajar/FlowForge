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
    public function __construct(private readonly JwtService $jwtService)
    {
    }

    /**
     * @param  array{name: string, email: string, password: string, tenant_name: string}  $data
     * @return array{user: User, token: string, refresh_token: string, token_type: string, expires_in: int}
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
            ...$this->jwtService->tokenPair($user),
        ];
    }

    /**
     * @param  array{email: string, password: string}  $credentials
     * @return array{user: User, token: string, refresh_token: string, token_type: string, expires_in: int}
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
            ...$this->jwtService->tokenPair($user),
        ];
    }

    /**
     * @return array{token: string, refresh_token: string, token_type: string, expires_in: int}
     */
    public function refresh(?string $refreshToken): array
    {
        $tokens = $this->jwtService->refresh($refreshToken);

        if ($tokens === null) {
            throw ValidationException::withMessages([
                'refresh_token' => ['The refresh token is invalid or expired.'],
            ])->status(401);
        }

        return $tokens;
    }

    public function logout(User $user): void
    {
        // JWT authentication is stateless; clients discard the token on logout.
    }
}
