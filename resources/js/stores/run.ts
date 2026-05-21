import { defineStore } from 'pinia';
import { ref } from 'vue';
import { api } from '../lib/axios';
import type { ApiResponse } from '../types/api.types';
import { RunStatus, type StepRun, type WorkflowRun } from '../types/run.types';

type RunPatch = Partial<WorkflowRun> & { id: string };

export const useRunStore = defineStore('run', () => {
    const currentRun = ref<WorkflowRun | null>(null);
    const loading = ref(false);

    const fetchRun = async (id: string): Promise<void> => {
        loading.value = true;
        try {
            const response = await api.get<ApiResponse<WorkflowRun>>(`/api/v1/runs/${id}`);
            currentRun.value = response.data.data;
        } finally {
            loading.value = false;
        }
    };

    const cancelRun = async (id: string): Promise<void> => {
        const response = await api.post<ApiResponse<WorkflowRun>>(`/api/v1/runs/${id}/cancel`);
        currentRun.value = response.data.data;
    };

    const updateStepRun = (stepRun: StepRun): void => {
        if (currentRun.value === null) {
            return;
        }

        const exists = currentRun.value.step_runs.some((item) => item.id === stepRun.id);
        currentRun.value.step_runs = exists
            ? currentRun.value.step_runs.map((item) => item.id === stepRun.id ? stepRun : item)
            : [...currentRun.value.step_runs, stepRun];
    };

    const updateRun = (run: RunPatch): void => {
        if (currentRun.value === null || currentRun.value.id !== run.id) {
            return;
        }

        currentRun.value = {
            ...currentRun.value,
            ...run,
            status: run.status === ('complete' as RunStatus) ? RunStatus.COMPLETED : run.status ?? currentRun.value.status,
            workflow: run.workflow ?? currentRun.value.workflow,
            step_runs: run.step_runs ?? currentRun.value.step_runs,
        };
    };

    return { currentRun, loading, fetchRun, cancelRun, updateStepRun, updateRun };
});
