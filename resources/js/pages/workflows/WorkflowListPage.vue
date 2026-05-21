<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import WorkflowPanel from '../../components/monitoring/WorkflowPanel.vue';
import RunPanel from '../../components/monitoring/RunPanel.vue';
import TracePanel from '../../components/monitoring/TracePanel.vue';
import WorkflowEditorPage from './WorkflowEditorPage.vue';
import { useAuthStore } from '../../stores/auth';
import { UserRole } from '../../types/auth.types';

const auth = useAuthStore();
const selectedWorkflowId = ref<string | null>(null);
const selectedRunId = ref<string | null>(null);
const runRefreshKey = ref(0);
const editorMode = ref<'create' | null>(null);
const canCreateWorkflow = computed(() => auth.user?.role === UserRole.ADMIN || auth.user?.role === UserRole.EDITOR);

const openCreateWorkflow = (): void => {
    if (!canCreateWorkflow.value) {
        return;
    }

    editorMode.value = 'create';
    selectedWorkflowId.value = null;
    selectedRunId.value = null;
};

const selectWorkflow = (workflowId: string): void => {
    editorMode.value = null;
    selectedWorkflowId.value = workflowId;
};

const selectRun = (runId: string): void => {
    selectedRunId.value = runId;
};

const handleRunTriggered = (workflowId: string, runId: string): void => {
    editorMode.value = null;
    selectedWorkflowId.value = workflowId;
    selectedRunId.value = runId;
    runRefreshKey.value++;
};

const handleWorkflowSaved = (workflowId: string): void => {
    editorMode.value = null;
    selectedWorkflowId.value = workflowId;
    selectedRunId.value = null;
};

watch(selectedWorkflowId, () => {
    selectedRunId.value = null;
});
</script>

<template>
    <main class="monitoring-page">
        <WorkflowPanel
            :selected-workflow-id="selectedWorkflowId"
            @add-workflow="openCreateWorkflow"
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
            v-if="editorMode === 'create'"
            embedded
            @saved="handleWorkflowSaved"
            @cancel="editorMode = null"
        />
        <TracePanel v-else-if="selectedRunId !== null" :run-id="selectedRunId" />
        <section v-else class="empty-panel">
            <span>{{ selectedWorkflowId === null ? 'Select a workflow' : 'Select a run' }}</span>
        </section>
    </main>
</template>

<style scoped>
.monitoring-page {
    display: flex;
    width: 100%;
    height: 100dvh;
    min-height: 0;
    overflow-x: auto;
    overflow-y: hidden;
    background: #f7fafc;
    overscroll-behavior-x: contain;
}

.empty-panel {
    display: grid;
    flex: 1;
    min-width: min(520px, 100vw);
    place-items: center;
    height: 100dvh;
    border-left: 1px solid #dbe3ef;
    color: #64748b;
    font-size: 14px;
}

@media (max-width: 900px) {
    .monitoring-page {
        align-items: stretch;
        scroll-snap-type: x proximity;
    }

    .empty-panel {
        min-width: 78vw;
        scroll-snap-align: start;
    }
}
</style>
