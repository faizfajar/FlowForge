<?php

namespace Tests;

use App\Models\User;
use App\Services\Auth\JwtService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Auth;

abstract class TestCase extends BaseTestCase
{
    protected function actingAsJwt(User $user): static
    {
        Auth::forgetGuards();

        return $this->withToken(app(JwtService::class)->make($user));
    }
}
