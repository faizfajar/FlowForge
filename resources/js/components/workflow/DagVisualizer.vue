<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { MarkerType, VueFlow, useVueFlow, type Edge, type Node, type NodeMouseEvent } from '@vue-flow/core';
import '@vue-flow/core/dist/style.css';
import type { WorkflowDag } from '../../types/workflow.types';
import { StepRunStatus } from '../../types/run.types';

const props = withDefaults(defineProps<{
    dag: WorkflowDag;
    stepStatuses?: Record<string, StepRunStatus>;
}>(), {
    stepStatuses: () => ({}),
});

const emit = defineEmits<{
    nodeClick: [stepId: string];
}>();

const { fitView } = useVueFlow();
const ready = ref(false);

const statusClass = (stepId: string): string => props.stepStatuses[stepId]?.toLowerCase() ?? 'pending';

const nodes = computed<Node[]>(() => props.dag.steps.map((step, index) => ({
    id: step.id,
    position: { x: (index % 3) * 260, y: Math.floor(index / 3) * 140 },
    data: { label: step.name || step.id },
    class: ['dag-node', statusClass(step.id), props.stepStatuses[step.id] === StepRunStatus.RUNNING ? 'pulse' : ''],
})));

const edges = computed<Edge[]>(() => props.dag.steps.flatMap((step) => step.dependencies.map((dependency) => ({
    id: `${dependency}-${step.id}`,
    source: dependency,
    target: step.id,
    markerEnd: MarkerType.ArrowClosed,
}))));

onMounted(() => {
    ready.value = true;
    window.setTimeout(() => fitView({ padding: 0.2 }), 60);
});

const handleNodeClick = (event: NodeMouseEvent): void => {
    emit('nodeClick', event.node.id);
};
</script>

<template>
    <VueFlow
        v-if="ready"
        class="dag-canvas"
        :nodes="nodes"
        :edges="edges"
        fit-view-on-init
        @node-click="handleNodeClick"
    >
    </VueFlow>
</template>

<style scoped>
.dag-canvas {
    height: 420px;
    border: 1px solid #dbe3ef;
    border-radius: 8px;
    background: #fbfdff;
}

:deep(.dag-node) {
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    background: #f1f5f9;
    color: #172033;
    padding: 10px;
}

:deep(.dag-node.running) { border-color: #2563eb; background: #dbeafe; }
:deep(.dag-node.success) { border-color: #16a34a; background: #dcfce7; }
:deep(.dag-node.failed) { border-color: #dc2626; background: #fee2e2; }
:deep(.dag-node.cancelled) { border-color: #64748b; background: #e2e8f0; }
:deep(.dag-node.pulse) { animation: pulse 1.2s infinite; }

@keyframes pulse {
    50% { box-shadow: 0 0 0 8px rgb(37 99 235 / 12%); }
}
</style>
