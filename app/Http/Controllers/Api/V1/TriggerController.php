<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\WorkflowRunResource;
use App\Services\Workflow\TriggerService;
use App\Services\Workflow\WorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TriggerController extends Controller
{
    public function __construct(
        private readonly TriggerService $triggerService,
        private readonly WorkflowService $workflowService,
    ) {
    }

    public function triggerWorkflow(string $id): JsonResponse
    {
        $workflow = $this->workflowService->show($id);
        $this->authorize('trigger', $workflow);

        return response()->json([
            'data' => new WorkflowRunResource($this->triggerService->triggerManual($id)),
            'message' => 'Workflow triggered.',
        ], Response::HTTP_ACCEPTED);
    }

    public function triggerWebhook(string $token, Request $request): JsonResponse
    {
        return response()->json([
            'data' => new WorkflowRunResource($this->triggerService->triggerWebhook($token, $request)),
            'message' => 'Webhook accepted.',
        ], Response::HTTP_ACCEPTED);
    }
}
