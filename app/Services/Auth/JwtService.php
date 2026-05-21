<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use RuntimeException;

class JwtService
{
    /**
     * @return array{token: string, refresh_token: string, token_type: string, expires_in: int}
     */
    public function tokenPair(User $user): array
    {
        return [
            'token' => $this->makeAccessToken($user),
            'refresh_token' => $this->makeRefreshToken($user),
            'token_type' => 'Bearer',
            'expires_in' => $this->ttl() * 60,
        ];
    }

    public function make(User $user): string
    {
        return $this->makeAccessToken($user);
    }

    public function makeAccessToken(User $user): string
    {
        $issuedAt = Carbon::now();
        $expiresAt = $issuedAt->copy()->addMinutes($this->ttl());

        return $this->encode([
            'typ' => 'access',
            'iss' => config('app.url'),
            'sub' => $user->getAuthIdentifier(),
            'iat' => $issuedAt->timestamp,
            'exp' => $expiresAt->timestamp,
            'tenant_id' => $user->tenant_id,
            'role' => $user->role->value,
        ]);
    }

    public function makeRefreshToken(User $user): string
    {
        $issuedAt = Carbon::now();
        $expiresAt = $issuedAt->copy()->addMinutes($this->refreshTtl());

        return $this->encode([
            'typ' => 'refresh',
            'iss' => config('app.url'),
            'sub' => $user->getAuthIdentifier(),
            'iat' => $issuedAt->timestamp,
            'exp' => $expiresAt->timestamp,
        ]);
    }

    /**
     * @return array{token: string, refresh_token: string, token_type: string, expires_in: int}|null
     */
    public function refresh(?string $refreshToken): ?array
    {
        if ($refreshToken === null || $refreshToken === '') {
            return null;
        }

        $payload = $this->decode($refreshToken);

        if (
            $payload === null
            || ($payload['typ'] ?? null) !== 'refresh'
            || ! isset($payload['sub'])
            || ! is_string($payload['sub'])
        ) {
            return null;
        }

        $user = User::query()->find($payload['sub']);

        return $user instanceof User ? $this->tokenPair($user) : null;
    }

    public function userFromToken(?string $token): ?Authenticatable
    {
        if ($token === null || $token === '') {
            return null;
        }

        $payload = $this->decode($token);

        if (
            $payload === null
            || ($payload['typ'] ?? null) !== 'access'
            || ! isset($payload['sub'])
            || ! is_string($payload['sub'])
        ) {
            return null;
        }

        return User::query()->find($payload['sub']);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function encode(array $payload): string
    {
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $segments = [
            $this->base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR)),
            $this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR)),
        ];

        $segments[] = $this->signature(implode('.', $segments));

        return implode('.', $segments);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function decode(string $token): ?array
    {
        $segments = explode('.', $token);

        if (count($segments) !== 3) {
            return null;
        }

        [$header, $payload, $signature] = $segments;
        $signedPayload = $header.'.'.$payload;

        if (! hash_equals($this->signature($signedPayload), $signature)) {
            return null;
        }

        $decoded = json_decode($this->base64UrlDecode($payload), true);

        if (! is_array($decoded)) {
            return null;
        }

        if (isset($decoded['exp']) && is_numeric($decoded['exp']) && (int) $decoded['exp'] < time()) {
            return null;
        }

        return $decoded;
    }

    private function signature(string $payload): string
    {
        return $this->base64UrlEncode(hash_hmac('sha256', $payload, $this->secret(), true));
    }

    private function secret(): string
    {
        $secret = config('jwt.secret') ?: config('app.key');

        if (! is_string($secret) || $secret === '') {
            throw new RuntimeException('JWT secret is not configured.');
        }

        if (str_starts_with($secret, 'base64:')) {
            $decoded = base64_decode(substr($secret, 7), true);

            if ($decoded === false) {
                throw new InvalidArgumentException('JWT secret base64 value is invalid.');
            }

            return $decoded;
        }

        return $secret;
    }

    private function ttl(): int
    {
        return (int) config('jwt.ttl', 60);
    }

    private function refreshTtl(): int
    {
        return (int) config('jwt.refresh_ttl', 10_080);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return $decoded === false ? '' : $decoded;
    }
}
