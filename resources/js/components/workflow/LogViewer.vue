<script setup lang="ts">
import { nextTick, ref, watch } from 'vue';
import type { ExecutionLog } from '../../types/run.types';

const props = withDefaults(defineProps<{
    logs: ExecutionLog[];
    autoScroll?: boolean;
}>(), {
    autoScroll: true,
});

const container = ref<HTMLElement | null>(null);

watch(() => props.logs.length, async () => {
    if (!props.autoScroll) return;
    await nextTick();
    if (container.value !== null) {
        container.value.scrollTop = container.value.scrollHeight;
    }
});
</script>

<template>
    <div ref="container" class="log-viewer">
        <article v-for="log in logs" :key="log.id" class="log-line">
            <time>{{ log.logged_at }}</time>
            <span class="level" :class="log.level.toLowerCase()">{{ log.level }}</span>
            <strong v-if="log.step_name">{{ log.step_name }}</strong>
            <span>{{ log.message }}</span>
        </article>
    </div>
</template>

<style scoped>
.log-viewer {
    max-height: 260px;
    overflow: auto;
    border: 1px solid #dbe3ef;
    border-radius: 8px;
    background: #0f172a;
    padding: 12px;
    color: #e2e8f0;
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
}

.log-line {
    display: grid;
    grid-template-columns: 170px 80px 120px 1fr;
    gap: 8px;
    padding: 5px 0;
}

.level {
    font-weight: 700;
}

.info { color: #cbd5e1; }
.warning { color: #fde68a; }
.error { color: #fca5a5; }
</style>
