<?php

declare(strict_types=1);

namespace App\Services\Workflow;

use App\Enums\TriggerType;
use App\Enums\WorkflowRunStatus;
use App\Jobs\ExecuteWorkflowJob;
use App\Models\User;
use App\Models\WebhookTrigger;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowRun;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TriggerService
{
    public function triggerManual(string $workflowId): WorkflowRun
    {
        /** @var User $user */
        $user = request()->user('api') ?? request()->user();
        $definition = WorkflowDefinition::query()
            ->with('activeVersion')
            ->where('tenant_id', $user->tenant_id)
            ->findOrFail($workflowId);

        if ($definition->activeVersion === null) {
            throw new NotFoundHttpException('Workflow has no active version.');
        }

        $run = $this->createRun($definition, TriggerType::MANUAL, $user->id, []);

        ExecuteWorkflowJob::dispatch($run->id)->onQueue('high');

        return $run->load(['definition', 'version']);
    }

    public function triggerScheduled(WorkflowDefinition $definition): WorkflowRun
    {
        if ($definition->activeVersion === null) {
            throw new NotFoundHttpException('Workflow has no active version.');
        }

        $run = $this->createRun($definition, TriggerType::SCHEDULED, null, []);
        ExecuteWorkflowJob::dispatch($run->id)->onQueue('high');

        return $run->load(['definition', 'version']);
    }

    public function triggerDueScheduledWorkflows(): int
    {
        $now = Carbon::now()->startOfMinute();
        $cron = app(CronExpression::class);
        $triggered = 0;

        WorkflowDefinition::withoutGlobalScopes()
            ->with('activeVersion')
            ->whereNotNull('schedule_cron')
            ->orderBy('id')
            ->chunkById(100, function ($definitions) use ($cron, $now, &$triggered): void {
                foreach ($definitions as $definition) {
                    if (! $definition instanceof WorkflowDefinition || ! is_string($definition->schedule_cron)) {
                        continue;
                    }

                    if ($definition->last_scheduled_run_at?->gte($now) || ! $cron->isDue($definition->schedule_cron, $now)) {
                        continue;
                    }

                    DB::transaction(function () use ($definition, $now, &$triggered): void {
                        $locked = WorkflowDefinition::withoutGlobalScopes()
                            ->with('activeVersion')
                            ->lockForUpdate()
                            ->find($definition->id);

                        if (! $locked instanceof WorkflowDefinition || $locked->last_scheduled_run_at?->gte($now)) {
                            return;
                        }

                        $this->triggerScheduled($locked);
                        $locked->forceFill(['last_scheduled_run_at' => $now])->save();
                        $triggered++;
                    });
                }
            });

        return $triggered;
    }

    /**
     * @return array<string, string>
     */
    public function ensureWebhookTrigger(string $workflowId): array
    {
        /** @var User $user */
        $user = request()->user('api') ?? request()->user();
        $definition = WorkflowDefinition::query()
            ->where('tenant_id', $user->tenant_id)
            ->findOrFail($workflowId);

        $trigger = WebhookTrigger::query()->firstOrCreate(
            [
                'workflow_definition_id' => $definition->id,
                'is_active' => true,
            ],
            [
                'tenant_id' => $definition->tenant_id,
                'token' => Str::random(48),
                'secret' => Str::random(64),
            ]
        );

        return [
            'token' => $trigger->token,
            'secret' => $trigger->secret,
            'url' => url("/api/v1/webhooks/{$trigger->token}/trigger"),
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function createRun(WorkflowDefinition $definition, TriggerType $triggerType, ?string $triggeredBy, array $metadata): WorkflowRun
    {
        $timeoutSeconds = max(1, (int) config('workflow.run_timeout_seconds', 1800));

        return WorkflowRun::withoutGlobalScopes()->create([
            'tenant_id' => $definition->tenant_id,
            'workflow_definition_id' => $definition->id,
            'workflow_version_id' => $definition->activeVersion->id,
            'status' => WorkflowRunStatus::PENDING,
            'trigger_type' => $triggerType,
            'triggered_by' => $triggeredBy,
            'timeout_at' => now()->addSeconds($timeoutSeconds),
            'metadata' => $metadata,
        ]);
    }

    public function triggerWebhook(string $token, Request $request): WorkflowRun
    {
        $trigger = WebhookTrigger::withoutGlobalScopes()
            ->with('workflowDefinition.activeVersion')
            ->where('token', $token)
            ->where('is_active', true)
            ->first();

        if (! $trigger instanceof WebhookTrigger || $trigger->workflowDefinition->activeVersion === null) {
            throw new NotFoundHttpException('Webhook trigger not found.');
        }

        $payload = $request->getContent();
        $expected = hash_hmac('sha256', $payload, $trigger->secret);
        $provided = (string) $request->header('X-Signature', '');

        if (! hash_equals($expected, $provided)) {
            throw new AccessDeniedHttpException('Invalid webhook signature.');
        }

        $decodedPayload = json_decode($payload, true);
        $run = $this->createRun($trigger->workflowDefinition, TriggerType::WEBHOOK, null, [
            'payload' => is_array($decodedPayload) ? $decodedPayload : [],
        ]);

        ExecuteWorkflowJob::dispatch($run->id)->onQueue('high');

        return $run->load(['definition', 'version']);
    }
}
