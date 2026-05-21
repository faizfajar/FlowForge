import { storeToRefs } from 'pinia';
import { ref } from 'vue';
import { useWorkflowStore, type WorkflowFilters } from '../stores/workflow';
import type { WorkflowForm } from '../types/workflow.types';

export function useWorkflows() {
    const store = useWorkflowStore();
    const { workflows, currentWorkflow, loading } = storeToRefs(store);
    const error = ref<string | null>(null);

    const wrap = async <T>(action: () => Promise<T>): Promise<T | null> => {
        error.value = null;
        try {
            return await action();
        } catch (caught: unknown) {
            error.value = caught instanceof Error ? caught.message : 'Workflow request failed.';
            return null;
        }
    };

    return {
        workflows,
        currentWorkflow,
        loading,
        error,
        fetchWorkflows: (filters?: WorkflowFilters) => wrap(() => store.fetchWorkflows(filters)),
        fetchWorkflow: (id: string) => wrap(() => store.fetchWorkflow(id)),
        createWorkflow: (data: WorkflowForm) => wrap(() => store.createWorkflow(data)),
        updateWorkflow: (id: string, data: WorkflowForm) => wrap(() => store.updateWorkflow(id, data)),
        deleteWorkflow: (id: string) => wrap(() => store.deleteWorkflow(id)),
        triggerWorkflow: (id: string) => wrap(() => store.triggerWorkflow(id)),
    };
}
