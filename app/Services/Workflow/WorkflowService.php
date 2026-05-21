<?php

declare(strict_types=1);

namespace App\Services\Workflow;

use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowVersion;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class WorkflowService
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function index(array $filters): CursorPaginator
    {
        $user = $this->currentUser();

        return WorkflowDefinition::query()
            ->with('activeVersion')
            ->where('tenant_id', $user->tenant_id)
            ->when(isset($filters['name']) && is_string($filters['name']), function (Builder $query) use ($filters): void {
                $query->where('name', 'like', '%'.$filters['name'].'%');
            })
            ->orderByDesc('created_at')
            ->cursorPaginate(15);
    }

    /**
     * @param  array{name: string, description?: string|null, dag: array<string, mixed>}  $data
     */
    public function store(array $data): WorkflowDefinition
    {
        /** @var User $user */
        $user = request()->user('api') ?? request()->user();

        return DB::transaction(function () use ($data, $user): WorkflowDefinition {
            $definition = WorkflowDefinition::query()->create([
                'tenant_id' => $user->tenant_id,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
            ]);

            $version = WorkflowVersion::query()->create([
                'workflow_definition_id' => $definition->id,
                'version_number' => 1,
                'dag' => $data['dag'],
                'is_active' => true,
                'created_by' => $user->id,
            ]);

            $definition->forceFill(['active_version_id' => $version->id])->save();

            return $definition->load('activeVersion');
        });
    }

    public function show(int|string $id): WorkflowDefinition
    {
        $user = $this->currentUser();

        return WorkflowDefinition::query()
            ->with('activeVersion')
            ->where('tenant_id', $user->tenant_id)
            ->findOrFail((string) $id);
    }

    /**
     * @param  array{name: string, description?: string|null, dag: array<string, mixed>}  $data
     */
    public function update(string $id, array $data): WorkflowDefinition
    {
        /** @var User $user */
        $user = request()->user('api') ?? request()->user();

        return DB::transaction(function () use ($id, $data, $user): WorkflowDefinition {
            $definition = WorkflowDefinition::query()->findOrFail($id);
            $latestVersion = (int) $definition->versions()->max('version_number');

            $definition->update([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
            ]);

            $definition->versions()->update(['is_active' => false]);

            $version = WorkflowVersion::query()->create([
                'workflow_definition_id' => $definition->id,
                'version_number' => $latestVersion + 1,
                'dag' => $data['dag'],
                'is_active' => true,
                'created_by' => $user->id,
            ]);

            $definition->forceFill(['active_version_id' => $version->id])->save();

            return $definition->load('activeVersion');
        });
    }

    public function destroy(string $id): void
    {
        WorkflowDefinition::query()->findOrFail($id)->delete();
    }

    public function getVersions(string $id): CursorPaginator
    {
        $definition = $this->show($id);

        return $definition->versions()
            ->with('creator')
            ->orderByDesc('version_number')
            ->cursorPaginate(15);
    }

    public function restoreVersion(string $definitionId, int $version): WorkflowDefinition
    {
        return DB::transaction(function () use ($definitionId, $version): WorkflowDefinition {
            $definition = $this->show($definitionId);
            $workflowVersion = $definition->versions()
                ->where('version_number', $version)
                ->first();

            if (! $workflowVersion instanceof WorkflowVersion) {
                throw new NotFoundHttpException('Workflow version not found.');
            }

            $definition->versions()->update(['is_active' => false]);
            $workflowVersion->forceFill(['is_active' => true])->save();
            $definition->forceFill(['active_version_id' => $workflowVersion->id])->save();

            return $definition->load('activeVersion');
        });
    }

    private function currentUser(): User
    {
        /** @var User $user */
        $user = request()->user('api') ?? request()->user();

        return $user;
    }
}
