<script setup lang="ts">
import { computed } from 'vue';
import { RunStatus, StepRunStatus } from '../../types/run.types';

const props = defineProps<{
    status: StepRunStatus | RunStatus;
}>();

const color = computed(() => {
    const normalized = props.status.toLowerCase();
    if (normalized === StepRunStatus.RUNNING || normalized === RunStatus.RUNNING) return 'blue';
    if (normalized === StepRunStatus.SUCCESS || normalized === RunStatus.COMPLETED) return 'green';
    if (normalized === StepRunStatus.FAILED || normalized === RunStatus.FAILED) return 'red';
    return 'gray';
});
</script>

<template>
    <span class="badge" :class="color">{{ status }}</span>
</template>

<style scoped>
.badge {
    display: inline-flex;
    align-items: center;
    min-height: 24px;
    border-radius: 999px;
    padding: 2px 9px;
    font-size: 12px;
    font-weight: 700;
}

.gray { background: #e2e8f0; color: #334155; }
.blue { background: #dbeafe; color: #1d4ed8; }
.green { background: #dcfce7; color: #15803d; }
.red { background: #fee2e2; color: #b91c1c; }
</style>
