<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkflowVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WorkflowCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_workflow_with_valid_dag(): void
    {
        $user = $this->createUser('admin@example.com', UserRole::ADMIN);

        $this->actingAsJwt($user)->postJson('/api/v1/workflows', [
            'name' => 'Workflow',
            'description' => 'Test workflow',
            'dag' => $this->validDag(),
        ])->assertCreated()->assertJsonPath('data.name', 'Workflow');
    }

    public function test_cannot_create_workflow_with_cyclic_dag(): void
    {
        $user = $this->createUser('admin@example.com', UserRole::ADMIN);

        $this->actingAsJwt($user)->postJson('/api/v1/workflows', [
            'name' => 'Workflow',
            'dag' => [
                'steps' => [
                    ['id' => 'step-a', 'type' => 'SCRIPT', 'name' => 'Step A', 'config' => ['expression' => '1'], 'dependencies' => ['step-b']],
                    ['id' => 'step-b', 'type' => 'SCRIPT', 'name' => 'Step B', 'config' => ['expression' => '1'], 'dependencies' => ['step-a']],
                ],
            ],
        ])->assertUnprocessable();
    }

    public function test_update_creates_new_version_and_preserves_old_version(): void
    {
        $user = $this->createUser('admin@example.com', UserRole::ADMIN);
        $workflowId = $this->createWorkflow($user);

        $this->actingAsJwt($user)->putJson("/api/v1/workflows/{$workflowId}", [
            'name' => 'Workflow updated',
            'dag' => $this->validDag('second'),
        ])->assertOk()->assertJsonPath('data.active_version.version_number', 2);

        $this->assertSame(2, WorkflowVersion::query()->where('workflow_definition_id', $workflowId)->count());
    }

    public function test_can_restore_previous_version(): void
    {
        $user = $this->createUser('admin@example.com', UserRole::ADMIN);
        $workflowId = $this->createWorkflow($user);

        $this->actingAsJwt($user)->putJson("/api/v1/workflows/{$workflowId}", [
            'name' => 'Workflow updated',
            'dag' => $this->validDag('second'),
        ])->assertOk();

        $this->actingAsJwt($user)->postJson("/api/v1/workflows/{$workflowId}/versions/1/restore")
            ->assertOk()
            ->assertJsonPath('data.active_version.version_number', 1);
    }

    public function test_list_is_scoped_to_tenant(): void
    {
        $tenantAUser = $this->createUser('a@example.com', UserRole::ADMIN, 'tenant-a');
        $tenantBUser = $this->createUser('b@example.com', UserRole::ADMIN, 'tenant-b');
        $this->createWorkflow($tenantAUser, 'Visible');
        $this->createWorkflow($tenantBUser, 'Hidden');

        $this->actingAsJwt($tenantAUser)->getJson('/api/v1/workflows')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Visible'])
            ->assertJsonMissing(['name' => 'Hidden']);
    }

    public function test_soft_delete_hides_from_list(): void
    {
        $user = $this->createUser('admin@example.com', UserRole::ADMIN);
        $workflowId = $this->createWorkflow($user);

        $this->actingAsJwt($user)->deleteJson("/api/v1/workflows/{$workflowId}")->assertOk();
        $this->actingAsJwt($user)->getJson('/api/v1/workflows')
            ->assertOk()
            ->assertJsonMissing(['id' => $workflowId]);
    }

    private function createWorkflow(User $user, string $name = 'Workflow'): string
    {
        $response = $this->actingAsJwt($user)->postJson('/api/v1/workflows', [
            'name' => $name,
            'dag' => $this->validDag(),
        ])->assertCreated();

        return (string) $response->json('data.id');
    }

    private function createUser(string $email, UserRole $role, string $slug = 'tenant'): User
    {
        Role::firstOrCreate(['name' => $role->value, 'guard_name' => 'web']);
        $tenant = Tenant::query()->create(['name' => $slug, 'slug' => $slug, 'settings' => []]);
        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'User',
            'email' => $email,
            'password' => Hash::make('password123'),
            'role' => $role,
        ]);
        $user->assignRole($role->value);

        return $user;
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function validDag(string $expression = '1 + 1'): array
    {
        return [
            'steps' => [
                ['id' => 'step-a', 'type' => 'SCRIPT', 'name' => 'Step A', 'config' => ['expression' => $expression], 'dependencies' => []],
            ],
        ];
    }
}
