<script setup lang="ts">
import { ref, watch } from 'vue';
import WorkflowPanel from '../../components/monitoring/WorkflowPanel.vue';
import RunPanel from '../../components/monitoring/RunPanel.vue';
import TracePanel from '../../components/monitoring/TracePanel.vue';

const selectedWorkflowId = ref<string | null>(null);
const selectedRunId = ref<string | null>(null);
const runRefreshKey = ref(0);

const selectWorkflow = (workflowId: string): void => {
    selectedWorkflowId.value = workflowId;
};

const selectRun = (runId: string): void => {
    selectedRunId.value = runId;
};

const handleRunTriggered = (workflowId: string, runId: string): void => {
    selectedWorkflowId.value = workflowId;
    selectedRunId.value = runId;
    runRefreshKey.value++;
};

watch(selectedWorkflowId, () => {
    selectedRunId.value = null;
});
</script>

<template>
    <main class="monitoring-page">
        <WorkflowPanel
            :selected-workflow-id="selectedWorkflowId"
            @select-workflow="selectWorkflow"
            @select-run="selectRun"
            @run-triggered="handleRunTriggered"
        />
        <RunPanel
            v-if="selectedWorkflowId !== null"
            :workflow-id="selectedWorkflowId"
            :selected-run-id="selectedRunId"
            :refresh-key="runRefreshKey"
            @select-run="selectRun"
        />
        <TracePanel v-if="selectedRunId !== null" :run-id="selectedRunId" />
        <section v-else class="empty-panel">
            <span>{{ selectedWorkflowId === null ? 'Select a workflow' : 'Select a run' }}</span>
        </section>
    </main>
</template>

<style scoped>
.monitoring-page {
    display: flex;
    width: 100%;
    height: 100vh;
    min-height: 0;
    overflow: hidden;
    background: #f7fafc;
}

.empty-panel {
    display: grid;
    flex: 1;
    min-width: 0;
    place-items: center;
    height: 100vh;
    border-left: 1px solid #dbe3ef;
    color: #64748b;
    font-size: 14px;
}
</style>
