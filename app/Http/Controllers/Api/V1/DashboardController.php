<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Dashboard\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboardService)
    {
    }

    public function stats(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user('api');

        return response()->json(['data' => $this->dashboardService->stats($user)]);
    }
}
