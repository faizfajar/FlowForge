<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AiController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\RunController;
use App\Http\Controllers\Api\V1\TriggerController;
use App\Http\Controllers\Api\V1\WorkflowController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::prefix('auth')->group(function (): void {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::middleware('auth:api')->group(function (): void {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::get('me', [AuthController::class, 'me']);
        });
    });

    Route::post('webhooks/{token}/trigger', [TriggerController::class, 'triggerWebhook']);

    Route::middleware(['auth:api', 'tenant', 'tenant.throttle'])->group(function (): void {
        Route::post('workflows/validate-dag', [WorkflowController::class, 'validateDag']);
        Route::apiResource('workflows', WorkflowController::class);
        Route::get('workflows/{workflow}/versions', [WorkflowController::class, 'versions']);
        Route::post('workflows/{workflow}/versions/{version}/restore', [WorkflowController::class, 'restoreVersion']);
        Route::post('workflows/{id}/trigger', [TriggerController::class, 'triggerWorkflow']);
        Route::post('workflows/{id}/webhook', [TriggerController::class, 'ensureWebhook']);

        Route::get('dashboard/stats', [DashboardController::class, 'stats']);
        Route::post('ai/generate-workflow', [AiController::class, 'generateWorkflow'])->middleware('ai.rate');

        Route::get('runs', [RunController::class, 'index']);
        Route::get('runs/{runId}', [RunController::class, 'show']);
        Route::post('runs/{runId}/cancel', [RunController::class, 'cancel']);
        Route::get('runs/{runId}/logs', [RunController::class, 'logs']);
    });
});
