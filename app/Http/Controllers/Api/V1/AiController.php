<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\AiGenerationException;
use App\Exceptions\AiUnavailableException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Ai\WorkflowGeneratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiController extends Controller
{
    public function __construct(private readonly WorkflowGeneratorService $workflowGeneratorService)
    {
    }

    public function generateWorkflow(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'prompt' => ['required', 'string', 'max:400'],
        ]);

        /** @var User $user */
        $user = $request->user('api');

        try {
            return response()->json([
                'data' => $this->workflowGeneratorService->generate($validated['prompt'], (string) $user->tenant_id),
            ]);
        } catch (AiUnavailableException) {
            return response()->json(['message' => 'AI feature temporarily unavailable'], 503);
        } catch (AiGenerationException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'errors' => ['dag' => $exception->details()],
            ], 422);
        }
    }
}
