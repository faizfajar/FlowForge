<script setup lang="ts">
withDefaults(defineProps<{
    rows?: number;
    height?: string;
}>(), {
    rows: 3,
    height: '18px',
});
</script>

<template>
    <div class="skeleton-list">
        <div
            v-for="row in rows"
            :key="row"
            class="skeleton-row"
            :style="{ height }"
        />
    </div>
</template>

<style scoped>
.skeleton-list {
    display: grid;
    gap: 10px;
}

.skeleton-row {
    overflow: hidden;
    border-radius: 6px;
    background: linear-gradient(90deg, #edf2f7 0%, #f8fafc 50%, #edf2f7 100%);
    background-size: 200% 100%;
    animation: shimmer 1.2s infinite;
}

@keyframes shimmer {
    to {
        background-position: -200% 0;
    }
}
</style>
