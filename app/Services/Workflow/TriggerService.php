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

        $run = WorkflowRun::query()->create([
            'tenant_id' => $user->tenant_id,
            'workflow_definition_id' => $definition->id,
            'workflow_version_id' => $definition->activeVersion->id,
            'status' => WorkflowRunStatus::PENDING,
            'trigger_type' => TriggerType::MANUAL,
            'triggered_by' => $user->id,
            'metadata' => [],
        ]);

        ExecuteWorkflowJob::dispatch($run->id)->onQueue('high');

        return $run->load(['definition', 'version']);
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

        $run = WorkflowRun::withoutGlobalScopes()->create([
            'tenant_id' => $trigger->tenant_id,
            'workflow_definition_id' => $trigger->workflow_definition_id,
            'workflow_version_id' => $trigger->workflowDefinition->activeVersion->id,
            'status' => WorkflowRunStatus::PENDING,
            'trigger_type' => TriggerType::WEBHOOK,
            'triggered_by' => null,
            'metadata' => ['payload' => json_decode($payload, true) ?: []],
        ]);

        ExecuteWorkflowJob::dispatch($run->id)->onQueue('high');

        return $run->load(['definition', 'version']);
    }
}
