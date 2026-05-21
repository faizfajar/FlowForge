<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Enums\WorkflowRunStatus;
use App\Models\User;
use App\Models\WorkflowRun;
use Illuminate\Support\Carbon;

class DashboardService
{
    public function stats(User $user): array
    {
        $since = now()->subDay();
        $today = now()->startOfDay();
        $completed = WorkflowRun::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('created_at', '>=', $since)
            ->whereIn('status', [WorkflowRunStatus::COMPLETED, WorkflowRunStatus::FAILED])
            ->get();

        $successCount = $completed->where('status', WorkflowRunStatus::COMPLETED)->count();
        $totalCompleted = $completed->count();
        $averageExecutionTime = $completed
            ->filter(fn (WorkflowRun $run): bool => $run->started_at !== null && $run->completed_at !== null)
            ->avg(fn (WorkflowRun $run): int => (int) $run->started_at->diffInSeconds($run->completed_at));

        return [
            'active_runs_count' => WorkflowRun::query()
                ->where('tenant_id', $user->tenant_id)
                ->where('status', WorkflowRunStatus::RUNNING)
                ->count(),
            'success_rate_last_24h' => $totalCompleted === 0 ? 0 : round($successCount / $totalCompleted * 100, 2),
            'average_execution_time_last_24h' => $averageExecutionTime === null ? 0 : round((float) $averageExecutionTime, 2),
            'total_runs_today' => WorkflowRun::query()
                ->where('tenant_id', $user->tenant_id)
                ->where('created_at', '>=', $today)
                ->count(),
            'runs_per_hour' => $this->runsPerHour($user),
            'recent_failed_runs' => WorkflowRun::query()
                ->with('definition')
                ->where('tenant_id', $user->tenant_id)
                ->where('status', WorkflowRunStatus::FAILED)
                ->latest()
                ->limit(5)
                ->get()
                ->map(fn (WorkflowRun $run): array => [
                    'id' => $run->id,
                    'workflow_name' => $run->definition?->name ?? 'Unknown workflow',
                    'failed_at' => $run->completed_at?->timezone(config('app.timezone'))->toIso8601String(),
                ])
                ->all(),
        ];
    }

    private function runsPerHour(User $user): array
    {
        $start = now()->subHours(11)->startOfHour();
        $runs = WorkflowRun::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('created_at', '>=', $start)
            ->get()
            ->groupBy(fn (WorkflowRun $run): string => $run->created_at->format('Y-m-d H:00'));

        return collect(range(0, 11))
            ->map(function (int $offset) use ($start, $runs): array {
                $hour = Carbon::parse($start)->addHours($offset)->format('Y-m-d H:00');

                return ['hour' => $hour, 'total' => $runs->get($hour, collect())->count()];
            })
            ->all();
    }
}
