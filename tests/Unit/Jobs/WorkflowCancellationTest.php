<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Enums\StepRunStatus;
use App\Enums\StepType;
use App\Enums\TriggerType;
use App\Enums\UserRole;
use App\Enums\WorkflowRunStatus;
use App\Jobs\CancelWorkflowJob;
use App\Jobs\ExecuteStepJob;
use App\Models\StepRun;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowRun;
use App\Models\WorkflowVersion;
use App\Services\Workflow\ExecutorFactory;
use App\Services\Workflow\Executors\StepExecutorInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WorkflowCancellationTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancel_workflow_job_marks_pending_and_running_steps_as_cancelled(): void
    {
        $run = $this->createRun();

        StepRun::query()->create([
            'workflow_run_id' => $run->id,
            'step_id' => 'pending-step',
            'step_type' => StepType::SCRIPT,
            'status' => StepRunStatus::PENDING,
            'input' => [],
            'attempt' => 1,
        ]);

        StepRun::query()->create([
            'workflow_run_id' => $run->id,
            'step_id' => 'running-step',
            'step_type' => StepType::DELAY,
            'status' => StepRunStatus::RUNNING,
            'input' => ['seconds' => 5],
            'attempt' => 1,
            'started_at' => Carbon::now(),
        ]);

        (new CancelWorkflowJob($run->id))->handle();

        $this->assertSame(WorkflowRunStatus::CANCELLED, $run->fresh()->status);
        $this->assertSame(2, StepRun::query()->where('workflow_run_id', $run->id)->where('status', StepRunStatus::CANCELLED)->count());
    }

    public function test_execute_step_job_keeps_step_cancelled_when_run_is_cancelled_mid_execution(): void
    {
        $run = $this->createRun();
        $step = [
            'id' => 'script-step',
            'type' => StepType::SCRIPT->value,
            'name' => 'Script Step',
            'config' => ['expression' => '1 + 1'],
            'dependencies' => [],
        ];

        $executor = new class($run->id) implements StepExecutorInterface
        {
            public function __construct(private readonly string $runId) {}

            public function execute(StepRun $stepRun, array $previousOutputs): array
            {
                WorkflowRun::query()->whereKey($this->runId)->update([
                    'status' => WorkflowRunStatus::CANCELLED,
                    'completed_at' => Carbon::now(),
                ]);

                return ['result' => 2];
            }
        };

        $factory = Mockery::mock(ExecutorFactory::class);
        $factory->shouldReceive('make')->once()->andReturn($executor);

        (new ExecuteStepJob($run->id, $step))->handle($factory);

        /** @var StepRun $stepRun */
        $stepRun = StepRun::query()->where('workflow_run_id', $run->id)->where('step_id', 'script-step')->firstOrFail();

        $this->assertSame(StepRunStatus::CANCELLED, $stepRun->status);
        $this->assertSame('Workflow run cancelled.', $stepRun->error);
        $this->assertNull($stepRun->output);
    }

    private function createRun(): WorkflowRun
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
                    ['id' => 'script-step', 'type' => 'SCRIPT', 'name' => 'Script Step', 'config' => ['expression' => '1 + 1'], 'dependencies' => []],
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
            'status' => WorkflowRunStatus::RUNNING,
            'trigger_type' => TriggerType::MANUAL,
            'triggered_by' => $user->id,
            'metadata' => [],
            'started_at' => Carbon::now(),
        ]);
    }
}
