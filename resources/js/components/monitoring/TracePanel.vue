<script setup lang="ts">
import { computed, onUnmounted, ref, watch } from 'vue';
import DagVisualizer from '../workflow/DagVisualizer.vue';
import StepStatusBadge from '../ui/StepStatusBadge.vue';
import ConfirmDialog from '../ui/ConfirmDialog.vue';
import StepTraceRow from './StepTraceRow.vue';
import { api } from '../../lib/axios';
import { useAuthStore } from '../../stores/auth';
import { useRunStore } from '../../stores/run';
import { useReverb } from '../../composables/useReverb';
import { RunStatus, StepRunStatus, type ExecutionLog, type StepRun, type WorkflowRun } from '../../types/run.types';
import { UserRole } from '../../types/auth.types';
import type { ApiResponse } from '../../types/api.types';
import type { WorkflowDag } from '../../types/workflow.types';
import { formatDurationMs } from '../../lib/datetime';

interface TraceStepRun extends StepRun {
    input?: unknown;
}

interface TraceExecutionLog extends ExecutionLog {
    step_run_id?: string | null;
}

const props = defineProps<{
    runId: string;
}>();

const auth = useAuthStore();
const runStore = useRunStore();
const reverb = useReverb();
const logs = ref<TraceExecutionLog[]>([]);
const confirmCancel = ref(false);
const activeRunChannel = ref<string | null>(null);

const currentRun = computed(() => runStore.currentRun);

const canCancel = computed(() => {
    const role = String(auth.user?.role ?? '').toLowerCase();
    const isAdmin = role === UserRole.ADMIN.toLowerCase();

    return isAdmin && currentRun.value?.status === RunStatus.RUNNING;
});

const duration = computed(() => {
    if (currentRun.value?.started_at === null || currentRun.value?.started_at === undefined) {
        return '-';
    }

    const end = currentRun.value.completed_at ?? new Date().toISOString();
    const started = new Date(currentRun.value.started_at).getTime();
    const completed = new Date(end).getTime();

    return formatDurationMs(completed - started);
});

const stepStatuses = computed<Record<string, StepRunStatus>>(() => Object.fromEntries(
    currentRun.value?.step_runs.map((step) => [step.step_id, step.status]) ?? [],
));

const dag = computed<WorkflowDag>(() => ({
    steps: currentRun.value?.dag?.steps ?? currentRun.value?.step_runs.map((step) => ({
        id: step.step_id,
        type: step.step_type,
        name: step.step_id,
        config: {},
        dependencies: [],
    })) ?? [],
}));

const traceSteps = computed<TraceStepRun[]>(() => currentRun.value?.step_runs as TraceStepRun[] | undefined ?? []);

const loadLogs = async (): Promise<void> => {
    const response = await api.get<ApiResponse<TraceExecutionLog[]>>(`/api/v1/runs/${props.runId}/logs`);
    logs.value = response.data.data;
};

const loadRun = async (): Promise<void> => {
    await runStore.fetchRun(props.runId);
    await loadLogs();
};

const updateStep = (stepRun: StepRun): void => {
    runStore.updateStepRun(stepRun);
};

const logsForStep = (step: StepRun): TraceExecutionLog[] => logs.value.filter((log) => {
    if (log.step_run_id !== undefined && log.step_run_id !== null) {
        return log.step_run_id === step.id;
    }

    return log.step_name === step.step_id;
});

const cancelRun = async (): Promise<void> => {
    if (currentRun.value !== null) {
        await runStore.cancelRun(currentRun.value.id);
        confirmCancel.value = false;
    }
};

const subscribe = (): void => {
    const tenantId = auth.user?.tenant.id;
    const workflowId = currentRun.value?.workflow.id;

    if (tenantId === undefined || workflowId === undefined) {
        return;
    }

    const channelName = `tenant.${tenantId}.workflow.${workflowId}`;
    if (activeRunChannel.value !== null && activeRunChannel.value !== channelName && window.Echo !== undefined) {
        window.Echo.leave(activeRunChannel.value);
    }

    activeRunChannel.value = channelName;
    reverb.subscribeToRun(props.runId, tenantId, workflowId, {
        onRunStarted: (run) => {
            runStore.updateRun(run);
        },
        onStepStarted: (stepRun) => {
            updateStep(stepRun);
        },
        onStepCompleted: (stepRun) => {
            updateStep(stepRun);
        },
        onStepFailed: (stepRun) => {
            updateStep(stepRun);
        },
        onRunCompleted: (run) => {
            runStore.updateRun(run);
        },
    });
};

watch(() => props.runId, async () => {
    await loadRun();
    subscribe();
}, { immediate: true });

onUnmounted(() => {
    if (activeRunChannel.value !== null && window.Echo !== undefined) {
        window.Echo.leave(activeRunChannel.value);
    }
});

</script>

<template>
    <section class="trace-panel">
        <header v-if="currentRun" class="trace-header">
            <div class="title-block">
                <h2>{{ currentRun.workflow.name }}</h2>
                <span>Duration {{ duration }}</span>
            </div>
            <StepStatusBadge :status="currentRun.status" />
            <button v-if="canCancel" type="button" @click="confirmCancel = true">Cancel</button>
        </header>

        <section v-if="currentRun" class="trace-content">
            <div class="dag-shell">
                <DagVisualizer :dag="dag" :step-statuses="stepStatuses" />
            </div>

            <section class="steps">
                <StepTraceRow
                    v-for="step in traceSteps"
                    :key="step.id"
                    :step="step"
                    :logs="logsForStep(step)"
                />
            </section>
        </section>

        <ConfirmDialog
            v-if="confirmCancel"
            title="Cancel run"
            message="Cancel this running workflow?"
            confirm-label="Cancel run"
            danger
            @confirm="cancelRun"
            @cancel="confirmCancel = false"
        />
    </section>
</template>

<style scoped>
.trace-panel {
    flex: 1 0 min(680px, 100vw);
    min-width: 0;
    height: 100%;
    overflow-y: auto;
    background: #f7fafc;
    scroll-snap-align: start;
    scrollbar-gutter: stable;
}

.trace-header {
    position: sticky;
    top: 0;
    z-index: 2;
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto auto;
    align-items: center;
    gap: 12px;
    border-bottom: 1px solid #dbe3ef;
    background: #f7fafc;
    padding: 14px 16px;
}

.title-block {
    display: grid;
    gap: 4px;
    min-width: 0;
}

h2 {
    overflow: hidden;
    margin: 0;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 16px;
}

.title-block span {
    color: #64748b;
    font-size: 12px;
}

.trace-header button {
    border: 1px solid #b91c1c;
    border-radius: 6px;
    background: white;
    padding: 7px 10px;
    color: #b91c1c;
    cursor: pointer;
}

.trace-content {
    display: grid;
    gap: 14px;
    padding: 14px;
}

.dag-shell {
    overflow: hidden;
    border-radius: 8px;
}

.dag-shell :deep(.dag-canvas) {
    height: 320px;
}

.steps {
    display: grid;
    gap: 8px;
}

@media (max-width: 1180px) {
    .trace-panel {
        flex-basis: 620px;
    }
}

@media (max-width: 720px) {
    .trace-panel {
        flex-basis: 96vw;
    }

    .trace-header {
        grid-template-columns: minmax(0, 1fr);
        align-items: stretch;
    }
}
</style>
