<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import StepStatusBadge from '../../components/ui/StepStatusBadge.vue';
import DagVisualizer from '../../components/workflow/DagVisualizer.vue';
import LogViewer from '../../components/workflow/LogViewer.vue';
import { useReverb } from '../../composables/useReverb';
import { useToast } from '../../composables/useToast';
import { api } from '../../lib/axios';
import { useAuthStore } from '../../stores/auth';
import { useRunStore } from '../../stores/run';
import { RunStatus, type ExecutionLog, type StepRun, StepRunStatus } from '../../types/run.types';
import { UserRole } from '../../types/auth.types';
import type { WorkflowDag } from '../../types/workflow.types';
import type { ApiResponse } from '../../types/api.types';

const route = useRoute();
const runStore = useRunStore();
const auth = useAuthStore();
const toast = useToast();
const reverb = useReverb();
const selectedStep = ref<StepRun | null>(null);
const logs = ref<ExecutionLog[]>([]);
const runId = computed(() => String(route.params.runId));
const canCancel = computed(() => auth.user?.role === UserRole.ADMIN && runStore.currentRun?.status === RunStatus.RUNNING);

const statusMap = computed<Record<string, StepRunStatus>>(() => Object.fromEntries(
    runStore.currentRun?.step_runs.map((step) => [step.step_id, step.status]) ?? [],
));

const dag = computed<WorkflowDag>(() => ({
    steps: runStore.currentRun?.dag?.steps ?? runStore.currentRun?.step_runs.map((step) => ({
        id: step.step_id,
        type: step.step_type,
        name: step.step_id,
        config: {},
        dependencies: [],
    })) ?? [],
}));

const loadLogs = async (): Promise<void> => {
    const response = await api.get<ApiResponse<ExecutionLog[]>>(`/api/v1/runs/${runId.value}/logs`);
    logs.value = response.data.data;
};

const updateStep = (stepRun: StepRun): void => {
    runStore.updateStepRun(stepRun);
    selectedStep.value = stepRun;
};

onMounted(async () => {
    await runStore.fetchRun(runId.value);
    await loadLogs();
    if (auth.user?.tenant.id !== undefined) {
        reverb.subscribeToRun(runId.value, auth.user.tenant.id, {
            onStepStarted: updateStep,
            onStepCompleted: updateStep,
            onStepFailed: updateStep,
            onRunCompleted: () => {
                toast.success('Workflow run completed.');
            },
        });
    }
});
</script>

<template>
    <section v-if="runStore.currentRun" class="run-page">
        <div class="left">
            <DagVisualizer :dag="dag" :step-statuses="statusMap" @node-click="(id) => selectedStep = runStore.currentRun?.step_runs.find((step) => step.step_id === id) ?? null" />
        </div>
        <aside class="right">
            <h1>{{ runStore.currentRun.workflow.name }}</h1>
            <StepStatusBadge :status="runStore.currentRun.status" />
            <dl>
                <dt>Started</dt><dd>{{ runStore.currentRun.started_at ?? '—' }}</dd>
                <dt>Duration</dt><dd>{{ runStore.currentRun.completed_at ?? 'Running' }}</dd>
            </dl>
            <button v-if="canCancel" type="button" @click="runStore.cancelRun(runStore.currentRun.id)">Cancel</button>
            <section>
                <h2>Steps</h2>
                <button v-for="step in runStore.currentRun.step_runs" :key="step.id" type="button" class="step" @click="selectedStep = step">
                    {{ step.step_id }} <StepStatusBadge :status="step.status" />
                </button>
                <pre v-if="selectedStep">{{ selectedStep.error ?? JSON.stringify(selectedStep.output, null, 2) }}</pre>
            </section>
            <LogViewer :logs="logs" />
        </aside>
    </section>
</template>

<style scoped>
.run-page { display: grid; grid-template-columns: 3fr 2fr; gap: 18px; }
.right { display: grid; gap: 14px; }
.step { display: flex; justify-content: space-between; width: 100%; border: 1px solid #dbe3ef; border-radius: 6px; background: white; padding: 9px; }
pre { max-height: 180px; overflow: auto; border-radius: 8px; background: #f1f5f9; padding: 12px; }
</style>
