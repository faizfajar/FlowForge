<script setup lang="ts">
import { computed, onUnmounted, ref, watch } from 'vue';
import { api } from '../../lib/axios';
import { useAuthStore } from '../../stores/auth';
import { useWorkflowStore } from '../../stores/workflow';
import type { ApiResponse, PaginatedResponse } from '../../types/api.types';
import { RunStatus, type WorkflowRun } from '../../types/run.types';
import { UserRole } from '../../types/auth.types';
import type { Workflow } from '../../types/workflow.types';
import { formatDateTime, formatDurationBetween } from '../../lib/datetime';
import StepStatusBadge from '../ui/StepStatusBadge.vue';
import LoadingSkeleton from '../ui/LoadingSkeleton.vue';
import ConfirmDialog from '../ui/ConfirmDialog.vue';

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
const workflowStore = useWorkflowStore();
const runs = ref<WorkflowRun[]>([]);
const loading = ref(false);
const workflowLoading = ref(false);
const webhookLoading = ref(false);
const webhookTriggerLoading = ref(false);
const confirmWebhookTrigger = ref(false);
const workflow = ref<Workflow | null>(null);
const webhook = ref<{ url: string; secret: string } | null>(null);
const activeChannel = ref<string | null>(null);
const channelName = computed(() => auth.user === null ? null : `tenant.${auth.user.tenant.id}.workflow.${props.workflowId}`);
const canManageWorkflow = computed(() => auth.user?.role === UserRole.ADMIN || auth.user?.role === UserRole.EDITOR);
const workflowStatus = computed(() => workflow.value?.last_run?.status ?? 'ready');
const hasWebhook = computed(() => webhook.value !== null);

const duration = (run: WorkflowRun): string => {
    return formatDurationBetween(run.started_at, run.completed_at, 'pending');
};

const syncWorkflowFromStore = (): void => {
    workflow.value = workflowStore.workflows.find((item) => item.id === props.workflowId) ?? workflow.value;
};

const loadWorkflow = async (): Promise<void> => {
    syncWorkflowFromStore();

    if (workflow.value !== null) {
        return;
    }

    workflowLoading.value = true;
    try {
        const response = await api.get<ApiResponse<Workflow>>(`/api/v1/workflows/${props.workflowId}`);
        workflow.value = response.data.data;
    } finally {
        workflowLoading.value = false;
    }
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

const ensureWebhook = async (): Promise<void> => {
    if (webhookLoading.value) {
        return;
    }

    webhookLoading.value = true;
    try {
        const response = await api.post<ApiResponse<{ url: string; secret: string }>>(`/api/v1/workflows/${props.workflowId}/webhook`);
        webhook.value = response.data.data;
    } finally {
        webhookLoading.value = false;
    }
};

const bytesToHex = (bytes: Uint8Array): string => {
    return Array.from(bytes)
        .map((value) => value.toString(16).padStart(2, '0'))
        .join('');
};

const buildWebhookPayload = (): Record<string, string | number | boolean> => {
    return {
        source: 'flowforge-ui',
        workflow_id: props.workflowId,
        triggered_at: new Date().toISOString(),
        manual_test: true,
    };
};

const signWebhookPayload = async (payload: string, secret: string): Promise<string> => {
    const encoder = new TextEncoder();
    const key = await window.crypto.subtle.importKey(
        'raw',
        encoder.encode(secret),
        { name: 'HMAC', hash: 'SHA-256' },
        false,
        ['sign'],
    );

    const signature = await window.crypto.subtle.sign('HMAC', key, encoder.encode(payload));

    return bytesToHex(new Uint8Array(signature));
};

const triggerWebhook = async (): Promise<void> => {
    if (webhook.value === null || webhookTriggerLoading.value) {
        return;
    }

    webhookTriggerLoading.value = true;
    confirmWebhookTrigger.value = false;

    try {
        const payloadObject = buildWebhookPayload();
        const payload = JSON.stringify(payloadObject);
        const signature = await signWebhookPayload(payload, webhook.value.secret);
        const response = await fetch(webhook.value.url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Signature': signature,
            },
            body: payload,
        });

        if (!response.ok) {
            throw new Error('Webhook trigger request failed.');
        }

        const body = await response.json() as ApiResponse<WorkflowRun>;
        const run = body.data;

        runs.value = [run, ...runs.value.filter((item) => item.id !== run.id)];
        workflowStore.patchWorkflowLastRun(props.workflowId, {
            id: run.id,
            status: run.status,
            trigger_type: run.trigger_type,
            started_at: run.started_at,
            completed_at: run.completed_at,
        });
        emit('selectRun', run.id);
    } finally {
        webhookTriggerLoading.value = false;
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

    syncWorkflowFromStore();
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
    workflow.value = null;
    webhook.value = null;
    await Promise.all([loadWorkflow(), loadRuns()]);
    subscribe();
}, { immediate: true });

watch(() => props.refreshKey, () => {
    void loadRuns();
});

watch(() => workflowStore.workflows, () => {
    syncWorkflowFromStore();
}, { deep: true });

onUnmounted(() => {
    unsubscribe();
});
</script>

<template>
    <aside class="run-panel">
        <header class="panel-header">
            <div class="header-top">
                <div class="title-block">
                    <h2>{{ workflow?.name ?? (workflowLoading ? 'Loading workflow...' : 'Runs') }}</h2>
                    <p>{{ workflow?.description ?? 'Run history and webhook trigger for the selected workflow.' }}</p>
                </div>
                <span class="status-pill">{{ workflowStatus }}</span>
            </div>

            <div v-if="canManageWorkflow" class="header-actions">
                <button type="button" class="secondary-button" :disabled="webhookLoading" @click="ensureWebhook">
                    {{ webhookLoading ? 'Preparing...' : 'Webhook endpoint' }}
                </button>
            </div>

            <section v-if="webhook !== null" class="webhook-card">
                <span>Webhook endpoint</span>
                <strong>{{ webhook.url }}</strong>
                <small>Secret: {{ webhook.secret }}</small>
                <button type="button" class="primary-button" :disabled="webhookTriggerLoading" @click="confirmWebhookTrigger = true">
                    {{ webhookTriggerLoading ? 'Triggering...' : 'Trigger webhook' }}
                </button>
            </section>
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

        <ConfirmDialog
            v-if="confirmWebhookTrigger"
            title="Trigger webhook"
            message="Send a signed webhook request for this workflow now?"
            confirm-label="Trigger webhook"
            @confirm="triggerWebhook"
            @cancel="confirmWebhookTrigger = false"
        />
    </aside>
</template>

<style scoped>
.run-panel {
    flex: 0 0 600px;
    width: 600px;
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
    display: grid;
    gap: 12px;
    border-bottom: 1px solid #dbe3ef;
    background: white;
    padding: 14px;
}

.header-top {
    display: grid;
    gap: 10px;
}

.title-block {
    display: grid;
    gap: 4px;
}

h2 {
    margin: 0;
    font-size: 15px;
}

.title-block p {
    margin: 0;
    color: #64748b;
    font-size: 12px;
    line-height: 1.5;
}

.status-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: fit-content;
    min-height: 24px;
    border-radius: 999px;
    background: #e2e8f0;
    padding: 2px 10px;
    color: #334155;
    font-size: 12px;
    font-weight: 700;
    text-transform: lowercase;
}

.header-actions {
    display: flex;
}

.secondary-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 36px;
    border: 1px solid #dbe3ef;
    border-radius: 6px;
    background: #f8fafc;
    padding: 7px 12px;
    color: #172033;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
}

.primary-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 36px;
    border: 1px solid #0f766e;
    border-radius: 6px;
    background: #0f766e;
    padding: 7px 12px;
    color: #ffffff;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
}

.primary-button:disabled,
.secondary-button:disabled {
    opacity: 0.7;
    cursor: wait;
}

.webhook-card {
    display: grid;
    gap: 6px;
    border: 1px solid #dbe3ef;
    border-left: 4px solid #0f766e;
    border-radius: 8px;
    background: #f8fafc;
    padding: 10px;
}

.webhook-card span,
.webhook-card small {
    color: #64748b;
    font-size: 12px;
}

.webhook-card strong {
    overflow-wrap: anywhere;
    color: #172033;
    font-size: 13px;
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
        flex-basis: 380px;
        width: 380px;
    }
}

@media (max-width: 720px) {
    .run-panel {
        flex-basis: min(92vw, 420px);
        width: min(92vw, 420px);
    }

    .header-actions {
        display: grid;
    }

    .secondary-button {
        width: 100%;
    }

    .run-top,
    .run-bottom {
        align-items: flex-start;
        flex-direction: column;
    }
}
</style>
