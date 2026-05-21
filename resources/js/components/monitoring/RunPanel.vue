<script setup lang="ts">
import { computed, onUnmounted, ref, watch } from 'vue';
import { api } from '../../lib/axios';
import { useAuthStore } from '../../stores/auth';
import type { PaginatedResponse } from '../../types/api.types';
import { RunStatus, type WorkflowRun } from '../../types/run.types';
import { formatDateTime, formatDurationBetween } from '../../lib/datetime';
import StepStatusBadge from '../ui/StepStatusBadge.vue';
import LoadingSkeleton from '../ui/LoadingSkeleton.vue';

interface RunEventPayload {
    run?: {
        id: string;
        workflow?: { id: string; name: string };
        workflow_definition_id?: string;
        status?: string;
        trigger_type?: string;
        started_at?: string | null;
        completed_at?: string | null;
        step_runs?: WorkflowRun['step_runs'];
    };
}

const props = defineProps<{
    workflowId: string;
    selectedRunId: string | null;
    refreshKey: number;
}>();

const emit = defineEmits<{
    selectRun: [runId: string];
}>();

const auth = useAuthStore();
const runs = ref<WorkflowRun[]>([]);
const loading = ref(false);
const activeChannel = ref<string | null>(null);
const channelName = computed(() => auth.user === null ? null : `tenant.${auth.user.tenant.id}.workflow.${props.workflowId}`);

const duration = (run: WorkflowRun): string => {
    return formatDurationBetween(run.started_at, run.completed_at, 'pending');
};

const loadRuns = async (): Promise<void> => {
    loading.value = true;
    try {
        const response = await api.get<PaginatedResponse<WorkflowRun>>('/api/v1/runs', {
            params: { workflow_definition_id: props.workflowId },
        });
        runs.value = response.data.data;
    } finally {
        loading.value = false;
    }
};

const patchRun = (payload: RunEventPayload): void => {
    if (payload.run === undefined) {
        return;
    }

    const normalizedStatus = payload.run.status === 'complete' ? RunStatus.COMPLETED : payload.run.status as RunStatus | undefined;
    const existing = runs.value.find((run) => run.id === payload.run?.id);

    if (existing === undefined) {
        runs.value = [{
            id: payload.run.id,
            workflow: payload.run.workflow ?? { id: props.workflowId, name: 'Workflow' },
            status: normalizedStatus ?? RunStatus.PENDING,
            trigger_type: payload.run.trigger_type ?? 'manual',
            started_at: payload.run.started_at ?? null,
            completed_at: payload.run.completed_at ?? null,
            step_runs: [],
        }, ...runs.value];
        return;
    }

    runs.value = runs.value.map((run) => run.id === payload.run?.id ? {
        ...run,
        status: normalizedStatus ?? run.status,
        trigger_type: payload.run.trigger_type ?? run.trigger_type,
        started_at: payload.run.started_at ?? run.started_at,
        completed_at: payload.run.completed_at ?? run.completed_at,
    } : run);
};

const subscribe = (): void => {
    if (window.Echo === undefined || channelName.value === null || activeChannel.value === channelName.value) {
        return;
    }

    unsubscribe();
    activeChannel.value = channelName.value;
    window.Echo.private(channelName.value)
        .listen('.WorkflowRunStarted', (payload: RunEventPayload) => {
            if (payload.run?.workflow_definition_id === props.workflowId) {
                patchRun(payload);
            }
        })
        .listen('.WorkflowRunCompleted', (payload: RunEventPayload) => {
            if (payload.run?.workflow_definition_id === props.workflowId) {
                patchRun(payload);
            }
        });
};

const unsubscribe = (): void => {
    if (window.Echo !== undefined && activeChannel.value !== null) {
        window.Echo.leave(activeChannel.value);
        activeChannel.value = null;
    }
};

watch(() => props.workflowId, async () => {
    await loadRuns();
    subscribe();
}, { immediate: true });

watch(() => props.refreshKey, () => {
    void loadRuns();
});

onUnmounted(() => {
    unsubscribe();
});
</script>

<template>
    <aside class="run-panel">
        <header class="panel-header">
            <h2>Runs</h2>
        </header>

        <LoadingSkeleton v-if="loading && runs.length === 0" :rows="6" height="56px" />

        <section v-else class="run-list">
            <button
                v-for="run in runs"
                :key="run.id"
                type="button"
                class="run-item"
                :class="{ active: run.id === props.selectedRunId }"
                @click="emit('selectRun', run.id)"
            >
                <span class="run-top">
                    <span :class="{ pulse: run.status === RunStatus.RUNNING }">
                        <StepStatusBadge :status="run.status" />
                    </span>
                    <strong>{{ run.trigger_type }}</strong>
                </span>
                <span class="run-bottom">
                    <time>{{ formatDateTime(run.started_at, 'pending') }}</time>
                    <span>{{ duration(run) }}</span>
                </span>
            </button>
            <p v-if="runs.length === 0" class="empty">No runs for this workflow.</p>
        </section>
    </aside>
</template>

<style scoped>
.run-panel {
    flex: 0 0 340px;
    width: 340px;
    height: 100%;
    overflow-y: auto;
    border-right: 1px solid #dbe3ef;
    background: white;
    scroll-snap-align: start;
    scrollbar-gutter: stable;
}

.panel-header {
    position: sticky;
    top: 0;
    z-index: 1;
    border-bottom: 1px solid #dbe3ef;
    background: white;
    padding: 14px;
}

h2 {
    margin: 0;
    font-size: 15px;
}

.run-list {
    display: grid;
    gap: 4px;
    padding: 8px;
}

.run-item {
    display: grid;
    gap: 8px;
    width: 100%;
    border: 1px solid transparent;
    border-radius: 8px;
    background: transparent;
    padding: 10px;
    text-align: left;
    cursor: pointer;
}

.run-item:hover,
.run-item.active {
    border-color: #0f766e;
    background: #f7fafc;
}

.run-top,
.run-bottom {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}

.run-top strong {
    color: #334155;
    font-size: 12px;
    text-transform: uppercase;
}

.run-bottom {
    color: #64748b;
    font-size: 12px;
}

.empty {
    margin: 16px 8px;
    color: #64748b;
    font-size: 13px;
}

.pulse {
    display: inline-flex;
    border-radius: 999px;
    animation: pulse 1.2s infinite;
}

@keyframes pulse {
    50% { box-shadow: 0 0 0 7px rgb(15 118 110 / 14%); }
}

@media (max-width: 1180px) {
    .run-panel {
        flex-basis: 320px;
        width: 320px;
    }
}

@media (max-width: 720px) {
    .run-panel {
        flex-basis: min(86vw, 340px);
        width: min(86vw, 340px);
    }

    .run-top,
    .run-bottom {
        align-items: flex-start;
        flex-direction: column;
    }
}
</style>
