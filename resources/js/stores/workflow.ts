import { defineStore } from 'pinia';
import { ref } from 'vue';
import { api } from '../lib/axios';
import type { ApiResponse, PaginatedResponse } from '../types/api.types';
import type { Workflow, WorkflowForm, WorkflowLastRun } from '../types/workflow.types';
import type { WorkflowRun } from '../types/run.types';

export interface WorkflowFilters {
    name?: string;
    status?: string;
    cursor?: string | null;
}

export const useWorkflowStore = defineStore('workflow', () => {
    const workflows = ref<Workflow[]>([]);
    const currentWorkflow = ref<Workflow | null>(null);
    const loading = ref(false);
    const nextCursor = ref<string | null>(null);

    const fetchWorkflows = async (filters: WorkflowFilters = {}): Promise<void> => {
        loading.value = true;
        try {
            const response = await api.get<PaginatedResponse<Workflow>>('/api/v1/workflows', { params: filters });
            workflows.value = filters.cursor ? [...workflows.value, ...response.data.data] : response.data.data;
            nextCursor.value = response.data.meta.next_cursor;
        } finally {
            loading.value = false;
        }
    };

    const fetchWorkflow = async (id: string): Promise<void> => {
        loading.value = true;
        try {
            const response = await api.get<ApiResponse<Workflow>>(`/api/v1/workflows/${id}`);
            currentWorkflow.value = response.data.data;
        } finally {
            loading.value = false;
        }
    };

    const createWorkflow = async (data: WorkflowForm): Promise<Workflow> => {
        const response = await api.post<ApiResponse<Workflow>>('/api/v1/workflows', data);
        workflows.value = [response.data.data, ...workflows.value];
        return response.data.data;
    };

    const updateWorkflow = async (id: string, data: WorkflowForm): Promise<Workflow> => {
        const response = await api.put<ApiResponse<Workflow>>(`/api/v1/workflows/${id}`, data);
        currentWorkflow.value = response.data.data;
        workflows.value = workflows.value.map((workflow) => workflow.id === id ? response.data.data : workflow);
        return response.data.data;
    };

    const deleteWorkflow = async (id: string): Promise<void> => {
        await api.delete(`/api/v1/workflows/${id}`);
        workflows.value = workflows.value.filter((workflow) => workflow.id !== id);
    };

    const triggerWorkflow = async (id: string): Promise<WorkflowRun> => {
        const response = await api.post<ApiResponse<WorkflowRun>>(`/api/v1/workflows/${id}/trigger`);
        return response.data.data;
    };

    const upsertWorkflow = (workflow: Workflow): void => {
        const index = workflows.value.findIndex((item) => item.id === workflow.id);

        if (index === -1) {
            workflows.value = [workflow, ...workflows.value];
            return;
        }

        workflows.value = workflows.value.map((item) => item.id === workflow.id ? workflow : item);

        if (currentWorkflow.value?.id === workflow.id) {
            currentWorkflow.value = workflow;
        }
    };

    const removeWorkflow = (id: string): void => {
        workflows.value = workflows.value.filter((workflow) => workflow.id !== id);

        if (currentWorkflow.value?.id === id) {
            currentWorkflow.value = null;
        }
    };

    const patchWorkflowLastRun = (workflowId: string, lastRun: WorkflowLastRun): void => {
        workflows.value = workflows.value.map((workflow) => workflow.id === workflowId ? {
            ...workflow,
            last_run: {
                id: lastRun.id,
                status: lastRun.status,
                trigger_type: lastRun.trigger_type,
                started_at: lastRun.started_at,
                completed_at: lastRun.completed_at,
            },
        } : workflow);
    };

    return {
        workflows,
        currentWorkflow,
        loading,
        nextCursor,
        fetchWorkflows,
        fetchWorkflow,
        createWorkflow,
        updateWorkflow,
        deleteWorkflow,
        triggerWorkflow,
        upsertWorkflow,
        removeWorkflow,
        patchWorkflowLastRun,
    };
});
