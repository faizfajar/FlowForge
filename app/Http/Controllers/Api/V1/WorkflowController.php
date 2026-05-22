<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workflow\StoreWorkflowRequest;
use App\Http\Requests\Workflow\UpdateWorkflowRequest;
use App\Http\Requests\Workflow\ValidateDagRequest;
use App\Http\Resources\WorkflowResource;
use App\Http\Resources\WorkflowVersionResource;
use App\Models\WorkflowDefinition;
use App\Services\Workflow\WorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WorkflowController extends Controller
{
    public function __construct(private readonly WorkflowService $workflowService) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'name' => ['nullable', 'string', 'max:160'],
            'status' => ['nullable', 'string', 'in:pending,running,completed,failed,cancelled'],
            'cursor' => ['nullable', 'string', 'max:500'],
        ]);
        $workflows = $this->workflowService->index($filters);

        return response()->json([
            'data' => WorkflowResource::collection($workflows->items()),
            'meta' => [
                'next_cursor' => $workflows->nextCursor()?->encode(),
                'per_page' => $workflows->perPage(),
            ],
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
            'meta' => [
                'next_cursor' => $versions->nextCursor()?->encode(),
                'per_page' => $versions->perPage(),
            ],
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

    public function validateDag(ValidateDagRequest $request): JsonResponse
    {
        $request->validated();

        return response()->json(['data' => ['valid' => true]]);
    }
}
