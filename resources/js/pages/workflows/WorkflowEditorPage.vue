<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AiWorkflowBuilder from '../../components/ai/AiWorkflowBuilder.vue';
import { api } from '../../lib/axios';
import { useWorkflowStore } from '../../stores/workflow';
import type { ValidationError } from '../../types/api.types';
import type { WorkflowDag } from '../../types/workflow.types';

const route = useRoute();
const router = useRouter();
const store = useWorkflowStore();
const workflowId = computed(() => route.params.id ? String(route.params.id) : null);
const name = ref('');
const description = ref('');
const scheduleCron = ref('');
const dagText = ref('{\n  "steps": []\n}');
const errors = ref<Record<string, string[]>>({});
const showAi = ref(false);

const parseDag = (): WorkflowDag | null => {
    try {
        return JSON.parse(dagText.value) as WorkflowDag;
    } catch {
        errors.value = { dag: ['DAG JSON is invalid.'] };
        return null;
    }
};

const validateDag = async (): Promise<void> => {
    const dag = parseDag();
    if (dag === null) return;
    await api.post('/api/v1/workflows/validate-dag', { dag });
    errors.value = {};
};

const save = async (): Promise<void> => {
    const dag = parseDag();
    if (dag === null) return;
    try {
        const payload = { name: name.value, description: description.value || null, schedule_cron: scheduleCron.value || null, dag };
        const workflow = workflowId.value === null
            ? await store.createWorkflow(payload)
            : await store.updateWorkflow(workflowId.value, payload);
        await router.push(`/workflows/${workflow.id}`);
    } catch (caught: unknown) {
        const validation = caught as Partial<ValidationError>;
        errors.value = validation.errors ?? {};
    }
};

const useAiDag = (dag: WorkflowDag): void => {
    dagText.value = JSON.stringify(dag, null, 2);
    showAi.value = false;
};

onMounted(async () => {
    if (workflowId.value !== null) {
        await store.fetchWorkflow(workflowId.value);
        if (store.currentWorkflow !== null) {
            name.value = store.currentWorkflow.name;
            description.value = store.currentWorkflow.description ?? '';
            scheduleCron.value = store.currentWorkflow.schedule_cron ?? '';
            dagText.value = JSON.stringify(store.currentWorkflow.active_version.dag, null, 2);
        }
    }
});
</script>

<template>
    <section class="page">
        <h1>{{ workflowId ? 'Edit workflow' : 'Create workflow' }}</h1>
        <label>Name<input v-model="name" required /></label>
        <p v-if="errors.name" class="error">{{ errors.name[0] }}</p>
        <label>Description<textarea v-model="description" /></label>
        <label>Schedule cron<input v-model="scheduleCron" placeholder="*/5 * * * *" /></label>
        <p v-if="errors.schedule_cron" class="error">{{ errors.schedule_cron[0] }}</p>
        <label>DAG JSON<pre contenteditable @input="dagText = ($event.target as HTMLElement).innerText">{{ dagText }}</pre></label>
        <p v-if="errors.dag" class="error">{{ errors.dag[0] }}</p>
        <footer>
            <button type="button" @click="validateDag">Validate</button>
            <button type="button" @click="showAi = true">Generate with AI</button>
            <button type="button" @click="save">Save</button>
        </footer>
        <AiWorkflowBuilder v-if="showAi" @use="useAiDag" @close="showAi = false" />
    </section>
</template>

<style scoped>
.page { display: grid; gap: 14px; max-width: 920px; }
label { display: grid; gap: 7px; font-weight: 700; }
input, textarea, pre { border: 1px solid #cbd5e1; border-radius: 8px; padding: 10px; background: white; }
pre { min-height: 320px; white-space: pre-wrap; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
footer { display: flex; gap: 10px; }
.error { color: #b91c1c; }
</style>
