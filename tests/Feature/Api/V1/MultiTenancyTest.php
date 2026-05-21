<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WebhookTrigger;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MultiTenancyTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_from_tenant_a_cannot_view_tenant_b_workflows(): void
    {
        $tenantAUser = $this->createUser('a@example.com', UserRole::ADMIN, 'tenant-a');
        $tenantBUser = $this->createUser('b@example.com', UserRole::ADMIN, 'tenant-b');
        $workflow = $this->createWorkflow($tenantBUser);

        $this->actingAsJwt($tenantAUser)->getJson("/api/v1/workflows/{$workflow->id}")
            ->assertNotFound();
    }

    public function test_user_from_tenant_a_cannot_trigger_tenant_b_workflows(): void
    {
        Queue::fake();
        $tenantAUser = $this->createUser('a@example.com', UserRole::ADMIN, 'tenant-a');
        $tenantBUser = $this->createUser('b@example.com', UserRole::ADMIN, 'tenant-b');
        $workflow = $this->createWorkflow($tenantBUser);

        $this->actingAsJwt($tenantAUser)->postJson("/api/v1/workflows/{$workflow->id}/trigger")
            ->assertNotFound();
    }

    public function test_webhook_trigger_only_works_for_correct_tenants_workflow(): void
    {
        Queue::fake();
        $user = $this->createUser('admin@example.com', UserRole::ADMIN, 'tenant-a');
        $workflow = $this->createWorkflow($user);
        $secret = 'top-secret';
        $payload = json_encode(['event' => 'created'], JSON_THROW_ON_ERROR);

        WebhookTrigger::withoutGlobalScopes()->create([
            'tenant_id' => $user->tenant_id,
            'workflow_definition_id' => $workflow->id,
            'token' => 'token-123',
            'secret' => $secret,
            'is_active' => true,
        ]);

        $this->postJson('/api/v1/webhooks/token-123/trigger', ['event' => 'created'], [
            'X-Signature' => hash_hmac('sha256', $payload, $secret),
        ])->assertAccepted()->assertJsonPath('data.workflow.id', $workflow->id);

        $this->postJson('/api/v1/webhooks/bad-token/trigger', ['event' => 'created'], [
            'X-Signature' => hash_hmac('sha256', $payload, $secret),
        ])->assertNotFound();
    }

    private function createWorkflow(User $user): WorkflowDefinition
    {
        $definition = WorkflowDefinition::withoutGlobalScopes()->create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Workflow',
            'description' => null,
        ]);

        $version = WorkflowVersion::query()->create([
            'workflow_definition_id' => $definition->id,
            'version_number' => 1,
            'dag' => [
                'steps' => [
                    ['id' => 'A', 'type' => 'SCRIPT', 'config' => ['expression' => '1 + 1'], 'dependencies' => []],
                ],
            ],
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $definition->forceFill(['active_version_id' => $version->id])->save();

        return $definition;
    }

    private function createUser(string $email, UserRole $role, string $slug): User
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
}
