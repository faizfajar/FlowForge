<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Enums\TriggerType;
use App\Enums\UserRole;
use App\Enums\WorkflowRunStatus;
use App\Jobs\ExecuteWorkflowJob;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowRun;
use App\Models\WorkflowVersion;
use App\Services\Workflow\DagParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WorkflowTimeoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_execute_workflow_job_fails_run_when_global_timeout_has_elapsed(): void
    {
        $run = $this->createRun(Carbon::now()->subSecond());

        (new ExecuteWorkflowJob($run->id))->handle(app(DagParser::class));

        $run->refresh();

        $this->assertSame(WorkflowRunStatus::FAILED, $run->status);
        $this->assertNotNull($run->completed_at);
    }

    private function createRun(Carbon $timeoutAt): WorkflowRun
    {
        $tenant = Tenant::query()->create([
            'name' => 'Tenant',
            'slug' => 'tenant',
            'settings' => [],
        ]);

        Role::firstOrCreate(['name' => UserRole::ADMIN->value, 'guard_name' => 'web']);

        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password123'),
            'role' => UserRole::ADMIN,
        ]);
        $user->assignRole(UserRole::ADMIN->value);

        $workflow = WorkflowDefinition::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Workflow',
            'description' => null,
        ]);

        $version = WorkflowVersion::query()->create([
            'workflow_definition_id' => $workflow->id,
            'version_number' => 1,
            'dag' => [
                'steps' => [
                    ['id' => 'step-a', 'type' => 'SCRIPT', 'name' => 'Step A', 'config' => ['expression' => '1 + 1'], 'dependencies' => []],
                ],
            ],
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $workflow->forceFill(['active_version_id' => $version->id])->save();

        return WorkflowRun::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'workflow_definition_id' => $workflow->id,
            'workflow_version_id' => $version->id,
            'status' => WorkflowRunStatus::PENDING,
            'trigger_type' => TriggerType::MANUAL,
            'triggered_by' => $user->id,
            'timeout_at' => $timeoutAt,
            'metadata' => [],
        ]);
    }
}
