<script setup lang="ts">
import { computed } from 'vue';

const model = defineModel<string>({ required: true });

const parts = computed(() => model.value.trim().split(/\s+/).filter(Boolean));
const valid = computed(() => parts.value.length === 5 || parts.value.length === 6);
const preview = computed(() => {
    if (!valid.value) return [];
    const now = new Date();
    return [1, 2, 3].map((offset) => new Date(now.getTime() + offset * 60 * 60 * 1000).toLocaleString());
});
</script>

<template>
    <label class="cron-input">
        <span>Cron expression</span>
        <input v-model="model" type="text" placeholder="*/5 * * * *" :aria-invalid="!valid" />
        <small v-if="!valid">Use 5 or 6 cron parts.</small>
        <ul v-else>
            <li v-for="time in preview" :key="time">{{ time }}</li>
        </ul>
    </label>
</template>

<style scoped>
.cron-input {
    display: grid;
    gap: 8px;
}

input {
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    padding: 9px 10px;
}

small {
    color: #b91c1c;
}
</style>
