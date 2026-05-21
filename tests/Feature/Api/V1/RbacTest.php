<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\TriggerType;
use App\Enums\UserRole;
use App\Enums\WorkflowRunStatus;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowRun;
use App\Models\WorkflowVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RbacTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_editor_and_viewer_have_expected_workflow_permissions(): void
    {
        Queue::fake();

        $tenant = Tenant::query()->create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'settings' => []]);
        $admin = $this->createUser('admin@example.com', UserRole::ADMIN, $tenant);
        $editor = $this->createUser('editor@example.com', UserRole::EDITOR, $tenant);
        $viewer = $this->createUser('viewer@example.com', UserRole::VIEWER, $tenant);

        $workflow = $this->createWorkflow($admin, 'Original workflow');

        $this->actingAsJwt($admin)
            ->postJson('/api/v1/workflows', $this->workflowPayload('Admin workflow'))
            ->assertCreated();

        $this->actingAsJwt($editor)
            ->postJson('/api/v1/workflows', $this->workflowPayload('Editor workflow'))
            ->assertCreated();

        $this->actingAsJwt($viewer)
            ->postJson('/api/v1/workflows', $this->workflowPayload('Viewer workflow'))
            ->assertForbidden();

        $this->actingAsJwt($viewer)
            ->getJson("/api/v1/workflows/{$workflow->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $workflow->id);

        $this->actingAsJwt($editor)
            ->putJson("/api/v1/workflows/{$workflow->id}", $this->workflowPayload('Editor updated'))
            ->assertOk();

        $this->actingAsJwt($viewer)
            ->putJson("/api/v1/workflows/{$workflow->id}", $this->workflowPayload('Viewer updated'))
            ->assertForbidden();

        $this->actingAsJwt($editor)
            ->postJson("/api/v1/workflows/{$workflow->id}/trigger")
            ->assertAccepted();

        $this->actingAsJwt($viewer)
            ->postJson("/api/v1/workflows/{$workflow->id}/trigger")
            ->assertForbidden();

        $this->actingAsJwt($editor)
            ->deleteJson("/api/v1/workflows/{$workflow->id}")
            ->assertForbidden();

        $this->actingAsJwt($admin)
            ->deleteJson("/api/v1/workflows/{$workflow->id}")
            ->assertOk();
    }

    public function test_run_cancellation_is_limited_to_admin_or_run_owner(): void
    {
        Queue::fake();

        $tenant = Tenant::query()->create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'settings' => []]);
        $admin = $this->createUser('admin@example.com', UserRole::ADMIN, $tenant);
        $editor = $this->createUser('editor@example.com', UserRole::EDITOR, $tenant);
        $otherEditor = $this->createUser('other-editor@example.com', UserRole::EDITOR, $tenant);
        $viewer = $this->createUser('viewer@example.com', UserRole::VIEWER, $tenant);

        $workflow = $this->createWorkflow($admin, 'Cancellable workflow');
        $run = $this->createRun($workflow, $editor);

        $this->actingAsJwt($viewer)
            ->postJson("/api/v1/runs/{$run->id}/cancel")
            ->assertForbidden();

        $this->actingAsJwt($otherEditor)
            ->postJson("/api/v1/runs/{$run->id}/cancel")
            ->assertForbidden();

        $this->actingAsJwt($editor)
            ->postJson("/api/v1/runs/{$run->id}/cancel")
            ->assertAccepted();

        $this->actingAsJwt($admin)
            ->postJson("/api/v1/runs/{$run->id}/cancel")
            ->assertAccepted();
    }

    private function createUser(string $email, UserRole $role, Tenant $tenant): User
    {
        Role::firstOrCreate(['name' => $role->value, 'guard_name' => 'web']);

        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => ucfirst($role->value),
            'email' => $email,
            'password' => Hash::make('password123'),
            'role' => $role,
        ]);

        $user->assignRole($role->value);

        return $user;
    }

    private function createWorkflow(User $user, string $name): WorkflowDefinition
    {
        $workflow = WorkflowDefinition::withoutGlobalScopes()->create([
            'tenant_id' => $user->tenant_id,
            'name' => $name,
            'description' => null,
        ]);

        $version = WorkflowVersion::query()->create([
            'workflow_definition_id' => $workflow->id,
            'version_number' => 1,
            'dag' => $this->validDag(),
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $workflow->forceFill(['active_version_id' => $version->id])->save();

        return $workflow;
    }

    private function createRun(WorkflowDefinition $workflow, User $triggeredBy): WorkflowRun
    {
        return WorkflowRun::withoutGlobalScopes()->create([
            'tenant_id' => $triggeredBy->tenant_id,
            'workflow_definition_id' => $workflow->id,
            'workflow_version_id' => $workflow->active_version_id,
            'status' => WorkflowRunStatus::PENDING,
            'trigger_type' => TriggerType::MANUAL,
            'triggered_by' => $triggeredBy->id,
            'metadata' => [],
        ]);
    }

    /**
     * @return array{name: string, dag: array<string, array<int, array<string, mixed>>>}
     */
    private function workflowPayload(string $name): array
    {
        return [
            'name' => $name,
            'dag' => $this->validDag(),
        ];
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function validDag(): array
    {
        return [
            'steps' => [
                ['id' => 'step-a', 'type' => 'SCRIPT', 'name' => 'Step A', 'config' => ['expression' => '1 + 1'], 'dependencies' => []],
            ],
        ];
    }
}
