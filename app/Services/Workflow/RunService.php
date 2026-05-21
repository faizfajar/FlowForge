<?php

declare(strict_types=1);

namespace App\Services\Workflow;

use App\Enums\WorkflowRunStatus;
use App\Jobs\CancelWorkflowJob;
use App\Models\ExecutionLog;
use App\Models\User;
use App\Models\WorkflowRun;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RunService
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function index(array $filters): CursorPaginator
    {
        $user = $this->currentUser();

        return WorkflowRun::query()
            ->with(['definition', 'version'])
            ->where('tenant_id', $user->tenant_id)
            ->when(isset($filters['status']) && is_string($filters['status']), fn (Builder $query): Builder => $query->where('status', $filters['status']))
            ->when(isset($filters['workflow_definition_id']) && is_string($filters['workflow_definition_id']), fn (Builder $query): Builder => $query->where('workflow_definition_id', $filters['workflow_definition_id']))
            ->when(isset($filters['date_from']) && is_string($filters['date_from']), fn (Builder $query): Builder => $query->where('created_at', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']) && is_string($filters['date_to']), fn (Builder $query): Builder => $query->where('created_at', '<=', $filters['date_to']))
            ->orderByDesc('created_at')
            ->cursorPaginate(15);
    }

    public function show(string $runId): WorkflowRun
    {
        $user = $this->currentUser();

        return WorkflowRun::query()
            ->with(['definition', 'version', 'stepRuns' => fn ($query) => $query->orderBy('started_at')])
            ->where('tenant_id', $user->tenant_id)
            ->findOrFail($runId);
    }

    public function cancel(string $runId): WorkflowRun
    {
        $run = $this->show($runId);

        if (in_array($run->status, [WorkflowRunStatus::PENDING, WorkflowRunStatus::RUNNING], true)) {
            CancelWorkflowJob::dispatch($run->id);
        }

        return $run;
    }

    public function streamLogs(string $runId): StreamedResponse
    {
        $run = $this->show($runId);

        return response()->stream(function () use ($run): void {
            $lastLoggedAt = null;
            $initialLogs = ExecutionLog::query()
                ->where('workflow_run_id', $run->id)
                ->orderByDesc('logged_at')
                ->limit(100)
                ->get()
                ->sortBy('logged_at');

            foreach ($initialLogs as $log) {
                $lastLoggedAt = $log->logged_at;
                echo 'data: '.json_encode($log->toArray())."\n\n";
            }

            while (! in_array($run->fresh()?->status, [
                WorkflowRunStatus::COMPLETED,
                WorkflowRunStatus::FAILED,
                WorkflowRunStatus::CANCELLED,
            ], true)) {
                usleep(500000);

                $query = ExecutionLog::query()
                    ->where('workflow_run_id', $run->id)
                    ->orderBy('logged_at');

                if ($lastLoggedAt instanceof Carbon) {
                    $query->where('logged_at', '>', $lastLoggedAt);
                }

                foreach ($query->get() as $log) {
                    $lastLoggedAt = $log->logged_at;
                    echo 'data: '.json_encode($log->toArray())."\n\n";
                }

                if (ob_get_level() > 0) {
                    ob_flush();
                }

                flush();
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    public function logs(string $runId): array
    {
        $run = $this->show($runId);

        return ExecutionLog::query()
            ->where('workflow_run_id', $run->id)
            ->orderBy('logged_at')
            ->limit(200)
            ->get()
            ->map(fn (ExecutionLog $log): array => [
                'id' => $log->id,
                'logged_at' => $log->logged_at?->toISOString(),
                'level' => $log->level,
                'message' => $log->message,
                'step_name' => $log->stepRun?->step_id,
            ])
            ->all();
    }

    private function currentUser(): User
    {
        /** @var User $user */
        $user = request()->user('api') ?? request()->user();

        return $user;
    }
}
