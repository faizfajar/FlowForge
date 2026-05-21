export enum StepType {
    HTTP_CALL = 'HTTP_CALL',
    SCRIPT = 'SCRIPT',
    DELAY = 'DELAY',
    CONDITION = 'CONDITION',
}

export interface WorkflowStep {
    id: string;
    type: StepType;
    name: string;
    config: Record<string, unknown>;
    dependencies: string[];
}

export interface WorkflowDag {
    steps: WorkflowStep[];
}

export interface WorkflowVersion {
    id: string;
    version_number: number;
    dag: WorkflowDag;
    is_active: boolean;
    created_at: string;
}

export interface Workflow {
    id: string;
    name: string;
    description: string | null;
    schedule_cron: string | null;
    last_scheduled_run_at: string | null;
    active_version: WorkflowVersion;
    last_run?: {
        id: string;
        status: string;
        trigger_type: string;
        started_at: string | null;
        completed_at: string | null;
    } | null;
    created_at: string;
}

export interface WorkflowForm {
    name: string;
    description?: string | null;
    schedule_cron?: string | null;
    dag: WorkflowDag;
}
