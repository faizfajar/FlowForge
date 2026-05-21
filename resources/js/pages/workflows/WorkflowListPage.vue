<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import WorkflowPanel from '../../components/monitoring/WorkflowPanel.vue';
import RunPanel from '../../components/monitoring/RunPanel.vue';
import TracePanel from '../../components/monitoring/TracePanel.vue';
import WorkflowEditorPage from './WorkflowEditorPage.vue';
import { api } from '../../lib/axios';
import { formatDurationMs } from '../../lib/datetime';
import { useAuthStore } from '../../stores/auth';
import type { ApiResponse } from '../../types/api.types';
import { UserRole } from '../../types/auth.types';

interface DashboardStats {
    active_runs_count: number;
    success_rate_last_24h: number;
    average_execution_time_last_24h: number;
    total_runs_today: number;
}

const auth = useAuthStore();
const selectedWorkflowId = ref<string | null>(null);
const selectedRunId = ref<string | null>(null);
const runRefreshKey = ref(0);
const editorMode = ref<'create' | 'edit' | null>(null);
const editingWorkflowId = ref<string | null>(null);
const canCreateWorkflow = computed(() => auth.user?.role === UserRole.ADMIN || auth.user?.role === UserRole.EDITOR);
const canViewGlobalHealth = computed(() => auth.user?.role === UserRole.ADMIN);
const dashboardStats = ref<DashboardStats | null>(null);
const statsTimer = ref<number | null>(null);

const fetchDashboardStats = async (): Promise<void> => {
    if (!canViewGlobalHealth.value) {
        dashboardStats.value = null;
        return;
    }

    const response = await api.get<ApiResponse<DashboardStats>>('/api/v1/dashboard/stats');
    dashboardStats.value = response.data.data;
};

const openCreateWorkflow = (): void => {
    if (!canCreateWorkflow.value) {
        return;
    }

    editorMode.value = 'create';
    editingWorkflowId.value = null;
    selectedWorkflowId.value = null;
    selectedRunId.value = null;
};

const openEditWorkflow = (workflowId: string): void => {
    if (!canCreateWorkflow.value) {
        return;
    }

    editorMode.value = 'edit';
    editingWorkflowId.value = workflowId;
    selectedWorkflowId.value = workflowId;
    selectedRunId.value = null;
};

const selectWorkflow = (workflowId: string): void => {
    editorMode.value = null;
    editingWorkflowId.value = null;
    selectedWorkflowId.value = workflowId;
};

const selectRun = (runId: string): void => {
    selectedRunId.value = runId;
};

const handleRunTriggered = (workflowId: string, runId: string): void => {
    editorMode.value = null;
    editingWorkflowId.value = null;
    selectedWorkflowId.value = workflowId;
    selectedRunId.value = runId;
    runRefreshKey.value++;
};

const handleWorkflowSaved = (workflowId: string): void => {
    editorMode.value = null;
    editingWorkflowId.value = null;
    selectedWorkflowId.value = workflowId;
    selectedRunId.value = null;
};

watch(selectedWorkflowId, () => {
    selectedRunId.value = null;
});

onMounted(async () => {
    if (canViewGlobalHealth.value) {
        await fetchDashboardStats();
        statsTimer.value = window.setInterval(() => void fetchDashboardStats(), 30000);
    }
});

onUnmounted(() => {
    if (statsTimer.value !== null) {
        window.clearInterval(statsTimer.value);
    }
});
</script>

<template>
    <main class="monitoring-shell">
        <section v-if="dashboardStats !== null" class="summary-strip">
            <article class="summary-card">
                <span>Active runs</span>
                <strong>{{ dashboardStats.active_runs_count }}</strong>
            </article>
            <article class="summary-card">
                <span>Success rate 24h</span>
                <strong>{{ dashboardStats.success_rate_last_24h }}%</strong>
            </article>
            <article class="summary-card">
                <span>Avg execution 24h</span>
                <strong>{{ formatDurationMs(dashboardStats.average_execution_time_last_24h * 1000) }}</strong>
            </article>
            <article class="summary-card">
                <span>Total today</span>
                <strong>{{ dashboardStats.total_runs_today }}</strong>
            </article>
        </section>

        <section class="monitoring-page">
            <WorkflowPanel
                :selected-workflow-id="selectedWorkflowId"
                @add-workflow="openCreateWorkflow"
                @edit-workflow="openEditWorkflow"
                @select-workflow="selectWorkflow"
                @select-run="selectRun"
                @run-triggered="handleRunTriggered"
            />
            <RunPanel
                v-if="selectedWorkflowId !== null && editorMode === null"
                :workflow-id="selectedWorkflowId"
                :selected-run-id="selectedRunId"
                :refresh-key="runRefreshKey"
                @select-run="selectRun"
            />
            <WorkflowEditorPage
                v-if="editorMode !== null"
                embedded
                :workflow-id-override="editorMode === 'edit' ? editingWorkflowId : null"
                @saved="handleWorkflowSaved"
                @cancel="editorMode = null; editingWorkflowId = null"
            />
            <TracePanel v-else-if="selectedRunId !== null" :run-id="selectedRunId" />
            <section v-else class="empty-panel">
                <span>{{ selectedWorkflowId === null ? 'Select a workflow' : 'Select a run' }}</span>
            </section>
        </section>
    </main>
</template>

<style scoped>
.monitoring-shell {
    display: grid;
    grid-template-rows: auto 1fr;
    width: 100%;
    height: 100dvh;
    min-height: 0;
    background: #f7fafc;
}

.summary-strip {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 10px;
    border-bottom: 1px solid #dbe3ef;
    background: #f7fafc;
    padding: 10px 12px;
}

.summary-card {
    display: grid;
    gap: 6px;
    border: 1px solid #dbe3ef;
    border-radius: 8px;
    background: white;
    padding: 12px;
}

.summary-card span {
    color: #64748b;
    font-size: 12px;
}

.summary-card strong {
    font-size: 22px;
}

.monitoring-page {
    display: flex;
    width: 100%;
    height: 100%;
    min-height: 0;
    overflow-x: auto;
    overflow-y: hidden;
    overscroll-behavior-x: contain;
}

.empty-panel {
    display: grid;
    flex: 1;
    min-width: min(520px, 100vw);
    place-items: center;
    height: 100%;
    border-left: 1px solid #dbe3ef;
    color: #64748b;
    font-size: 14px;
}

@media (max-width: 900px) {
    .summary-strip {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        padding: 10px;
    }

    .monitoring-page {
        align-items: stretch;
        scroll-snap-type: x proximity;
    }

    .empty-panel {
        min-width: 78vw;
        scroll-snap-align: start;
    }
}

@media (max-width: 640px) {
    .summary-strip {
        grid-template-columns: 1fr;
    }

    .summary-card strong {
        font-size: 18px;
    }
}
</style>
