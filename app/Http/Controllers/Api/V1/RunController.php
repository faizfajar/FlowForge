<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\RunCollection;
use App\Http\Resources\RunDetailResource;
use App\Http\Resources\WorkflowRunResource;
use App\Services\Workflow\RunService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RunController extends Controller
{
    public function __construct(private readonly RunService $runService)
    {
    }

    public function index(Request $request): RunCollection
    {
        return new RunCollection($this->runService->index($request->query()));
    }

    public function show(string $runId): JsonResponse
    {
        $run = $this->runService->show($runId);
        $this->authorize('view', $run);

        return response()->json(['data' => new RunDetailResource($run)]);
    }

    public function cancel(string $runId): JsonResponse
    {
        $run = $this->runService->show($runId);
        $this->authorize('cancel', $run);

        return response()->json([
            'data' => new WorkflowRunResource($this->runService->cancel($runId)),
            'message' => 'Workflow cancellation accepted.',
        ], Response::HTTP_ACCEPTED);
    }

    public function logs(Request $request, string $runId): JsonResponse|StreamedResponse
    {
        $run = $this->runService->show($runId);
        $this->authorize('view', $run);

        if ($request->expectsJson()) {
            return response()->json(['data' => $this->runService->logs($runId)]);
        }

        return $this->runService->streamLogs($runId);
    }
}
