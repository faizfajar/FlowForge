<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import StepStatusBadge from '../ui/StepStatusBadge.vue';
import LogViewer from '../workflow/LogViewer.vue';
import { StepRunStatus, type ExecutionLog, type StepRun } from '../../types/run.types';
import { StepType } from '../../types/workflow.types';
import { formatDurationBetween } from '../../lib/datetime';

type TraceTab = 'input' | 'output' | 'error';

interface TraceStepRun extends StepRun {
    input?: unknown;
}

const props = defineProps<{
    step: TraceStepRun;
    logs: ExecutionLog[];
}>();

const expanded = ref(props.step.status === StepRunStatus.FAILED);
const activeTab = ref<TraceTab>(props.step.status === StepRunStatus.FAILED ? 'error' : 'input');

const icon = computed(() => {
    if (props.step.step_type === StepType.HTTP_CALL) return 'HTTP';
    if (props.step.step_type === StepType.SCRIPT) return 'JS';
    if (props.step.step_type === StepType.DELAY) return 'WAIT';
    if (props.step.step_type === StepType.CONDITION) return 'IF';

    return 'STEP';
});

const duration = computed(() => {
    return formatDurationBetween(
        props.step.started_at,
        props.step.completed_at,
        props.step.status === StepRunStatus.RUNNING ? 'running' : '-',
    );
});

const prettyInput = computed(() => JSON.stringify(props.step.input ?? null, null, 2));
const prettyOutput = computed(() => JSON.stringify(props.step.output ?? null, null, 2));

const setTab = (tab: TraceTab): void => {
    if (tab === 'error' && props.step.error === null) {
        return;
    }

    activeTab.value = tab;
};

watch(() => props.step.status, (status) => {
    if (status === StepRunStatus.FAILED) {
        expanded.value = true;
        activeTab.value = 'error';
    }
});
</script>

<template>
    <article class="trace-row" :class="{ running: step.status === StepRunStatus.RUNNING }">
        <button type="button" class="row-summary" @click="expanded = !expanded">
            <span class="step-icon">{{ icon }}</span>
            <span class="step-title">
                <strong>{{ step.step_id }}</strong>
                <small>{{ step.step_type }}</small>
            </span>
            <StepStatusBadge :status="step.status" />
            <span class="duration">{{ duration }}</span>
        </button>

        <section v-if="expanded" class="row-detail">
            <nav class="tabs">
                <button type="button" :class="{ active: activeTab === 'input' }" @click="setTab('input')">Input</button>
                <button type="button" :class="{ active: activeTab === 'output' }" @click="setTab('output')">Output</button>
                <button
                    type="button"
                    :class="{ active: activeTab === 'error' }"
                    :disabled="step.error === null"
                    @click="setTab('error')"
                >
                    Error
                </button>
            </nav>

            <pre v-if="activeTab === 'input'">{{ prettyInput }}</pre>
            <pre v-if="activeTab === 'output'">{{ prettyOutput }}</pre>
            <pre v-if="activeTab === 'error'" class="error">{{ step.error }}</pre>

            <LogViewer v-if="logs.length > 0" :logs="logs" />
        </section>
    </article>
</template>

<style scoped>
.trace-row {
    border: 1px solid #dbe3ef;
    border-radius: 8px;
    background: white;
}

.trace-row.running {
    animation: pulse 1.2s infinite;
}

.row-summary {
    display: grid;
    grid-template-columns: 44px minmax(0, 1fr) auto 58px;
    align-items: center;
    gap: 10px;
    width: 100%;
    border: 0;
    background: transparent;
    padding: 10px;
    color: #172033;
    text-align: left;
    cursor: pointer;
}

.step-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 28px;
    border-radius: 6px;
    background: #e6fffb;
    color: #0f766e;
    font-size: 10px;
    font-weight: 800;
}

.step-title {
    display: grid;
    gap: 2px;
    min-width: 0;
}

.step-title strong,
.step-title small {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.step-title small,
.duration {
    color: #64748b;
    font-size: 11px;
}

.row-detail {
    display: grid;
    gap: 10px;
    border-top: 1px solid #dbe3ef;
    padding: 10px;
}

.tabs {
    display: flex;
    gap: 6px;
}

.tabs button {
    border: 1px solid #dbe3ef;
    border-radius: 6px;
    background: white;
    padding: 5px 9px;
    color: #334155;
    cursor: pointer;
}

.tabs button.active {
    border-color: #0f766e;
    color: #0f766e;
}

.tabs button:disabled {
    color: #94a3b8;
    cursor: not-allowed;
}

pre {
    max-height: 220px;
    overflow: auto;
    border-radius: 8px;
    background: #f7fafc;
    padding: 12px;
    color: #172033;
    font-size: 12px;
}

pre.error {
    background: #fff1f2;
    color: #b91c1c;
}

@keyframes pulse {
    50% { box-shadow: 0 0 0 7px rgb(15 118 110 / 12%); }
}
</style>
