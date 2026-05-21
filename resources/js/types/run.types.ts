import type { StepType, WorkflowDag } from './workflow.types';

export enum RunStatus {
    PENDING = 'pending',
    RUNNING = 'running',
    COMPLETED = 'completed',
    FAILED = 'failed',
    CANCELLED = 'cancelled',
}

export enum StepRunStatus {
    PENDING = 'pending',
    RUNNING = 'running',
    SUCCESS = 'success',
    FAILED = 'failed',
    SKIPPED = 'skipped',
    CANCELLED = 'cancelled',
}

export interface StepRun {
    id: string;
    step_id: string;
    step_type: StepType;
    status: StepRunStatus;
    output: unknown;
    error: string | null;
    started_at: string | null;
    completed_at: string | null;
}

export interface WorkflowRun {
    id: string;
    workflow: { id: string; name: string };
    version_number?: number;
    dag?: WorkflowDag;
    status: RunStatus;
    trigger_type: string;
    started_at: string | null;
    completed_at: string | null;
    step_runs: StepRun[];
}

export interface ExecutionLog {
    id: string;
    logged_at: string;
    level: 'INFO' | 'WARNING' | 'ERROR' | string;
    message: string;
    step_name?: string | null;
}
