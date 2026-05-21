<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import LoadingSkeleton from '../ui/LoadingSkeleton.vue';
import StepStatusBadge from '../ui/StepStatusBadge.vue';
import ConfirmDialog from '../ui/ConfirmDialog.vue';
import { useAuthStore } from '../../stores/auth';
import { useWorkflowStore } from '../../stores/workflow';
import { RunStatus, type WorkflowRun } from '../../types/run.types';
import { UserRole } from '../../types/auth.types';
import type { Workflow } from '../../types/workflow.types';
import { formatDateTime } from '../../lib/datetime';

interface WorkflowListPayload {
    action?: 'created' | 'updated' | 'deleted';
    workflow_id?: string;
    workflow?: Workflow | null;
}

interface WorkflowRunPayload {
    workflow?: {
        id: string;
        last_run: {
            id: string;
            status: string;
            trigger_type: string;
            started_at: string | null;
            completed_at: string | null;
        };
    };
}

const props = defineProps<{
    selectedWorkflowId: string | null;
}>();

const emit = defineEmits<{
    addWorkflow: [];
    editWorkflow: [workflowId: string];
    selectWorkflow: [workflowId: string];
    selectRun: [runId: string];
    runTriggered: [workflowId: string, runId: string];
}>();

const auth = useAuthStore();
const route = useRoute();
const router = useRouter();
const workflowStore = useWorkflowStore();
const search = ref('');
const statusFilter = ref('');
const pendingTriggerWorkflow = ref<Workflow | null>(null);
const triggeringWorkflowId = ref<string | null>(null);
const activeChannel = ref<string | null>(null);
let searchTimer = 0;

const validStatuses = new Set(Object.values(RunStatus));

const canCreate = computed(() => {
    const role = String(auth.user?.role ?? '').toLowerCase();

    return role === UserRole.ADMIN.toLowerCase() || role === UserRole.EDITOR.toLowerCase();
});

const canTrigger = canCreate;

const load = async (): Promise<void> => {
    await workflowStore.fetchWorkflows({
        name: search.value || undefined,
        status: statusFilter.value || undefined,
    });
};

const trigger = async (): Promise<void> => {
    if (pendingTriggerWorkflow.value === null || triggeringWorkflowId.value !== null) {
        return;
    }

    const workflow = pendingTriggerWorkflow.value;
    triggeringWorkflowId.value = workflow.id;
    pendingTriggerWorkflow.value = null;

    try {
        const run: WorkflowRun = await workflowStore.triggerWorkflow(workflow.id);
        workflowStore.patchWorkflowLastRun(workflow.id, {
            id: run.id,
            status: run.status,
            trigger_type: run.trigger_type,
            started_at: run.started_at,
            completed_at: run.completed_at,
        });
        emit('runTriggered', workflow.id, run.id);
    } finally {
        triggeringWorkflowId.value = null;
    }
};

const requestTrigger = (workflow: Workflow): void => {
    emit('selectWorkflow', workflow.id);
    pendingTriggerWorkflow.value = workflow;
};

const statusOf = (workflow: Workflow): RunStatus | null => {
    const status = workflow.last_run?.status;

    return Object.values(RunStatus).includes(status as RunStatus) ? status as RunStatus : null;
};

const syncRouteFilters = (): void => {
    const rawSearch = route.query.search;
    const rawStatus = route.query.status;
    const nextSearch = typeof rawSearch === 'string' ? rawSearch : '';
    const nextStatus = typeof rawStatus === 'string' && validStatuses.has(rawStatus as RunStatus) ? rawStatus : '';

    if (search.value !== nextSearch) {
        search.value = nextSearch;
    }

    if (statusFilter.value !== nextStatus) {
        statusFilter.value = nextStatus;
    }
};

const updateQuery = async (): Promise<void> => {
    const nextQuery = {
        ...route.query,
        search: search.value !== '' ? search.value : undefined,
        status: statusFilter.value !== '' ? statusFilter.value : undefined,
    };

    await router.replace({ query: nextQuery });
};

const subscribe = (): void => {
    const tenantId = auth.user?.tenant.id;
    const channelName = tenantId === undefined ? null : `tenant.${tenantId}.workflows`;

    if (window.Echo === undefined || channelName === null || activeChannel.value === channelName) {
        return;
    }

    if (activeChannel.value !== null) {
        window.Echo.leave(activeChannel.value);
    }

    activeChannel.value = channelName;
    window.Echo.private(channelName)
        .listen('.WorkflowDefinitionChanged', (payload: WorkflowListPayload) => {
            if ((payload.action === 'created' || payload.action === 'updated') && payload.workflow !== null && payload.workflow !== undefined) {
                workflowStore.upsertWorkflow(payload.workflow);
            }

            if (payload.action === 'deleted' && payload.workflow_id !== undefined) {
                workflowStore.removeWorkflow(payload.workflow_id);
            }
        })
        .listen('.WorkflowRunStarted', (payload: WorkflowRunPayload) => {
            if (payload.workflow !== undefined) {
                workflowStore.patchWorkflowLastRun(payload.workflow.id, payload.workflow.last_run);
            }
        })
        .listen('.WorkflowRunCompleted', (payload: WorkflowRunPayload) => {
            if (payload.workflow !== undefined) {
                workflowStore.patchWorkflowLastRun(payload.workflow.id, payload.workflow.last_run);
            }
        });
};

watch(search, () => {
    window.clearTimeout(searchTimer);
    searchTimer = window.setTimeout(() => void updateQuery(), 300);
});

watch(statusFilter, () => {
    void updateQuery();
});

watch(() => auth.user?.tenant.id, subscribe);
watch(() => [route.query.search, route.query.status], () => {
    syncRouteFilters();
    void load();
});

onMounted(() => {
    syncRouteFilters();
    void load();
    subscribe();
});

onUnmounted(() => {
    if (window.Echo !== undefined && activeChannel.value !== null) {
        window.Echo.leave(activeChannel.value);
    }
});
</script>

<template>
    <aside class="workflow-panel">
        <header class="panel-header">
            <div class="header-row">
                <h2>Workflows</h2>
                <button v-if="canCreate" type="button" class="add-button" @click="emit('addWorkflow')">Add Workflow +</button>
            </div>
            <input v-model="search" type="search" placeholder="Search workflows" />
            <select v-model="statusFilter" aria-label="Filter workflows by latest run status">
                <option value="">All statuses</option>
                <option :value="RunStatus.PENDING">Pending</option>
                <option :value="RunStatus.RUNNING">Running</option>
                <option :value="RunStatus.COMPLETED">Completed</option>
                <option :value="RunStatus.FAILED">Failed</option>
                <option :value="RunStatus.CANCELLED">Cancelled</option>
            </select>
        </header>

        <LoadingSkeleton v-if="workflowStore.loading && workflowStore.workflows.length === 0" :rows="6" height="58px" />

        <section v-else class="workflow-list">
            <button
                v-for="workflow in workflowStore.workflows"
                :key="workflow.id"
                type="button"
                class="workflow-item"
                :class="{ active: workflow.id === props.selectedWorkflowId }"
                @click="emit('selectWorkflow', workflow.id)"
            >
                <span class="item-main">
                    <strong>{{ workflow.name }}</strong>
                    <time>{{ formatDateTime(workflow.created_at) }}</time>
                </span>
                <span class="item-meta">
                    <StepStatusBadge v-if="statusOf(workflow) !== null" :status="statusOf(workflow) as RunStatus" />
                    <span v-else class="ready-badge">ready</span>
                    <span v-if="canTrigger" class="item-actions">
                        <button
                            type="button"
                            class="secondary-button"
                            @click.stop="emit('editWorkflow', workflow.id)"
                        >
                            Edit
                        </button>
                        <button
                            type="button"
                            class="trigger-button"
                            :disabled="triggeringWorkflowId !== null"
                            @click.stop="requestTrigger(workflow)"
                        >
                            <span v-if="triggeringWorkflowId === workflow.id" class="spinner"></span>
                            <span>{{ triggeringWorkflowId === workflow.id ? 'Running' : 'Run' }}</span>
                        </button>
                    </span>
                </span>
            </button>
        </section>

        <ConfirmDialog
            v-if="pendingTriggerWorkflow !== null"
            title="Run workflow"
            :message="`Trigger ${pendingTriggerWorkflow.name} now?`"
            confirm-label="Run"
            @confirm="trigger"
            @cancel="pendingTriggerWorkflow = null"
        />
    </aside>
</template>

<style scoped>
.workflow-panel {
    flex: 0 0 280px;
    width: 280px;
    height: 100%;
    overflow-y: auto;
    border-right: 1px solid #dbe3ef;
    background: #f7fafc;
    scroll-snap-align: start;
    scrollbar-gutter: stable;
}

.panel-header {
    position: sticky;
    top: 0;
    z-index: 1;
    display: grid;
    gap: 10px;
    border-bottom: 1px solid #dbe3ef;
    background: #f7fafc;
    padding: 14px;
}

h2 {
    margin: 0;
    font-size: 15px;
}

.header-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    min-width: 0;
}

.add-button {
    border: 1px solid #0f766e;
    border-radius: 6px;
    background: #0f766e;
    padding: 6px 8px;
    color: white;
    font-size: 11px;
    font-weight: 700;
    white-space: nowrap;
    cursor: pointer;
}

input {
    width: 100%;
    border: 1px solid #dbe3ef;
    border-radius: 6px;
    padding: 9px 10px;
    background: white;
}

select {
    width: 100%;
    border: 1px solid #dbe3ef;
    border-radius: 6px;
    padding: 9px 10px;
    background: white;
    color: #172033;
}

.workflow-list {
    display: grid;
    gap: 4px;
    padding: 8px;
}

.workflow-item {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 10px;
    width: 100%;
    border: 1px solid transparent;
    border-radius: 8px;
    background: transparent;
    padding: 10px;
    color: #172033;
    text-align: left;
    cursor: pointer;
}

.workflow-item:hover,
.workflow-item.active {
    border-color: #0f766e;
    background: white;
}

.item-main,
.item-meta {
    display: grid;
    gap: 6px;
    min-width: 0;
}

.item-main strong {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 13px;
}

time {
    color: #64748b;
    font-size: 11px;
}

.ready-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 24px;
    border-radius: 999px;
    background: #e2e8f0;
    padding: 2px 9px;
    color: #334155;
    font-size: 12px;
    font-weight: 700;
}

.item-actions {
    display: inline-flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 6px;
}

.secondary-button,
.trigger-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    border-radius: 6px;
    padding: 4px 8px;
    font-size: 12px;
    cursor: pointer;
}

.secondary-button {
    border: 1px solid #dbe3ef;
    background: white;
    color: #172033;
}

.trigger-button {
    border: 1px solid #0f766e;
    background: white;
    color: #0f766e;
}

.trigger-button:disabled {
    opacity: 0.7;
    cursor: wait;
}

.spinner {
    width: 12px;
    height: 12px;
    border: 2px solid #99f6e4;
    border-top-color: #0f766e;
    border-radius: 999px;
    animation: spin 0.8s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

@media (max-width: 1180px) {
    .workflow-panel {
        flex-basis: 300px;
        width: 300px;
    }

    .workflow-item {
        grid-template-columns: minmax(0, 1fr);
    }

    .item-meta {
        grid-template-columns: auto minmax(0, 1fr);
        align-items: center;
        justify-content: space-between;
    }

    .item-actions {
        justify-content: flex-end;
    }
}

@media (max-width: 720px) {
    .workflow-panel {
        flex-basis: min(86vw, 340px);
        width: min(86vw, 340px);
    }

    .header-row {
        align-items: stretch;
        flex-direction: column;
    }

    .add-button {
        width: 100%;
        min-height: 34px;
    }

    .item-meta {
        grid-template-columns: 1fr;
    }

    .item-actions {
        justify-content: stretch;
    }

    .secondary-button,
    .trigger-button {
        flex: 1 1 0;
        min-height: 34px;
    }
}
</style>
