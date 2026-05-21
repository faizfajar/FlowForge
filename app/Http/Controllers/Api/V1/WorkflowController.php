<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workflow\StoreWorkflowRequest;
use App\Http\Requests\Workflow\UpdateWorkflowRequest;
use App\Http\Resources\WorkflowResource;
use App\Http\Resources\WorkflowVersionResource;
use App\Models\WorkflowDefinition;
use App\Services\Workflow\WorkflowService;
use App\Rules\ValidDag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WorkflowController extends Controller
{
    public function __construct(private readonly WorkflowService $workflowService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $workflows = $this->workflowService->index($request->query());

        return response()->json([
            'data' => WorkflowResource::collection($workflows->items()),
            'meta' => ['next_cursor' => $workflows->nextCursor()?->encode()],
        ]);
    }

    public function store(StoreWorkflowRequest $request): JsonResponse
    {
        $this->authorize('create', WorkflowDefinition::class);
        $workflow = $this->workflowService->store($request->validated());

        return response()->json([
            'data' => new WorkflowResource($workflow),
            'message' => 'Workflow created.',
        ], Response::HTTP_CREATED);
    }

    public function show(string $workflow): JsonResponse
    {
        $definition = $this->workflowService->show($workflow);
        $this->authorize('view', $definition);

        return response()->json(['data' => new WorkflowResource($definition)]);
    }

    public function update(UpdateWorkflowRequest $request, string $workflow): JsonResponse
    {
        $definition = $this->workflowService->show($workflow);
        $this->authorize('update', $definition);

        return response()->json([
            'data' => new WorkflowResource($this->workflowService->update($workflow, $request->validated())),
            'message' => 'Workflow updated.',
        ]);
    }

    public function destroy(string $workflow): JsonResponse
    {
        $definition = $this->workflowService->show($workflow);
        $this->authorize('delete', $definition);
        $this->workflowService->destroy($workflow);

        return response()->json(['data' => null, 'message' => 'Workflow deleted.']);
    }

    public function versions(string $workflow): JsonResponse
    {
        $definition = $this->workflowService->show($workflow);
        $this->authorize('view', $definition);
        $versions = $this->workflowService->getVersions($workflow);

        return response()->json([
            'data' => WorkflowVersionResource::collection($versions->items()),
            'meta' => ['next_cursor' => $versions->nextCursor()?->encode()],
        ]);
    }

    public function restoreVersion(string $workflow, int $version): JsonResponse
    {
        $definition = $this->workflowService->show($workflow);
        $this->authorize('update', $definition);

        return response()->json([
            'data' => new WorkflowResource($this->workflowService->restoreVersion($workflow, $version)),
            'message' => 'Workflow version restored.',
        ]);
    }

    public function validateDag(Request $request, ValidDag $validDag): JsonResponse
    {
        $request->validate([
            'dag' => ['required', 'array', $validDag],
        ]);

        return response()->json(['data' => ['valid' => true]]);
    }
}
