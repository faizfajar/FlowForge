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
const props = withDefaults(defineProps<{
    embedded?: boolean;
    workflowIdOverride?: string | null;
}>(), {
    embedded: false,
    workflowIdOverride: null,
});

const emit = defineEmits<{
    saved: [workflowId: string];
    cancel: [];
}>();

const workflowId = computed(() => props.workflowIdOverride ?? (route.params.id ? String(route.params.id) : null));
const name = ref('');
const description = ref('');
const scheduleCron = ref('');
const dagText = ref('{\n  "steps": []\n}');
const errors = ref<Record<string, string[]>>({});
const showAi = ref(false);
const alertState = ref<{
    type: 'success' | 'error';
    title: string;
    message: string;
    details: string[];
} | null>(null);

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
    try {
        await api.post('/api/v1/workflows/validate-dag', { dag });
        errors.value = {};
        alertState.value = {
            type: 'success',
            title: 'DAG JSON valid',
            message: 'Workflow definition can be saved and executed.',
            details: [`${dag.steps.length} step${dag.steps.length === 1 ? '' : 's'} detected.`],
        };
    } catch (caught: unknown) {
        const validation = caught as Partial<ValidationError>;
        const details = Object.values(validation.errors ?? {}).flat();
        alertState.value = {
            type: 'error',
            title: 'DAG JSON invalid',
            message: validation.message ?? 'Please review the workflow definition.',
            details: details.length > 0 ? details : ['DAG validation failed.'],
        };
    }
};

const save = async (): Promise<void> => {
    const dag = parseDag();
    if (dag === null) return;
    try {
        const payload = { name: name.value, description: description.value || null, schedule_cron: scheduleCron.value || null, dag };
        const workflow = workflowId.value === null
            ? await store.createWorkflow(payload)
            : await store.updateWorkflow(workflowId.value, payload);

        if (props.embedded) {
            emit('saved', workflow.id);
            return;
        }

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
    <section class="page" :class="{ embedded }">
        <header class="editor-header">
            <div>
                <h1>{{ workflowId ? 'Edit workflow' : 'Create workflow' }}</h1>
                <p>Define workflow metadata and DAG JSON in one place.</p>
            </div>
            <button v-if="embedded" type="button" class="secondary" @click="emit('cancel')">Cancel</button>
        </header>
        <div class="editor-body">
            <label>Name<input v-model="name" required /></label>
            <p v-if="errors.name" class="error">{{ errors.name[0] }}</p>
            <label>Description<textarea v-model="description" /></label>
            <label>Schedule cron<input v-model="scheduleCron" placeholder="*/5 * * * *" /></label>
            <p v-if="errors.schedule_cron" class="error">{{ errors.schedule_cron[0] }}</p>
            <label class="dag-field">
                <span>DAG JSON</span>
                <textarea v-model="dagText" class="dag-input" spellcheck="false" />
            </label>
            <p v-if="errors.dag" class="error">{{ errors.dag[0] }}</p>
            <footer class="editor-footer">
                <button type="button" @click="validateDag">Validate</button>
                <button type="button" @click="showAi = true">Generate with AI</button>
                <button type="button" @click="save">Save</button>
            </footer>
        </div>
        <AiWorkflowBuilder v-if="showAi" @use="useAiDag" @close="showAi = false" />

        <section v-if="alertState !== null" class="alert-backdrop" role="dialog" aria-modal="true">
            <article class="alert-dialog" :class="alertState.type">
                <strong>{{ alertState.title }}</strong>
                <p>{{ alertState.message }}</p>
                <ul>
                    <li v-for="detail in alertState.details" :key="detail">{{ detail }}</li>
                </ul>
                <button type="button" @click="alertState = null">OK</button>
            </article>
        </section>
    </section>
</template>

<style scoped>
.page { display: grid; gap: 14px; max-width: 920px; }
.page.embedded {
    display: grid;
    grid-template-rows: auto minmax(0, 1fr);
    gap: 0;
    flex: 1 0 min(720px, 100vw);
    max-width: none;
    min-width: min(720px, 100vw);
    height: 100%;
    min-height: 0;
    background: #f7fafc;
    scroll-snap-align: start;
    scrollbar-gutter: stable;
}
.editor-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    padding: 18px 18px 14px;
    border-bottom: 1px solid #dbe3ef;
    background: #f7fafc;
}
h1 { margin: 0; font-size: 22px; }
.editor-header p { margin: 5px 0 0; color: #64748b; font-size: 13px; }
.editor-body {
    display: grid;
    align-content: start;
    gap: 14px;
    min-height: 0;
    overflow-y: auto;
    padding: 18px;
}
label { display: grid; gap: 7px; font-weight: 700; }
input, textarea { border: 1px solid #cbd5e1; border-radius: 8px; padding: 10px; background: white; }
textarea { min-height: 108px; resize: vertical; }
.dag-field { min-height: 0; }
.dag-input {
    min-height: 320px;
    height: min(48dvh, 560px);
    overflow: auto;
    padding: 12px;
    resize: none;
    white-space: pre;
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
}
.editor-footer {
    position: sticky;
    bottom: 0;
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    padding: 12px;
    border: 1px solid #dbe3ef;
    border-radius: 8px;
    background: rgb(247 250 252 / 96%);
    backdrop-filter: blur(8px);
}
button {
    border: 1px solid #0f766e;
    border-radius: 6px;
    background: #0f766e;
    padding: 9px 12px;
    color: white;
    font-weight: 700;
    cursor: pointer;
}
button.secondary {
    border-color: #cbd5e1;
    background: white;
    color: #334155;
}
.error { color: #b91c1c; }
.alert-backdrop {
    position: fixed;
    inset: 0;
    z-index: 20;
    display: grid;
    place-items: center;
    background: rgb(15 23 42 / 36%);
    padding: 18px;
}
.alert-dialog {
    display: grid;
    gap: 10px;
    width: min(440px, 100%);
    border: 1px solid #dbe3ef;
    border-top: 4px solid #0f766e;
    border-radius: 8px;
    background: white;
    padding: 18px;
    box-shadow: 0 18px 50px rgb(15 23 42 / 18%);
}
.alert-dialog.error { border-top-color: #b91c1c; }
.alert-dialog strong { font-size: 16px; }
.alert-dialog p { margin: 0; color: #475569; }
.alert-dialog ul { margin: 0; padding-left: 18px; color: #334155; }
.alert-dialog button { justify-self: end; min-width: 82px; }

@media (max-width: 720px) {
    .page.embedded {
        flex-basis: 96vw;
        min-width: 96vw;
    }

    .editor-header {
        padding: 14px;
    }

    .editor-body {
        padding: 14px;
    }

    .editor-header,
    .editor-footer {
        align-items: stretch;
        flex-direction: column;
    }

    .editor-footer button,
    .editor-header button {
        width: 100%;
    }

    .dag-input {
        height: min(42dvh, 420px);
    }
}
</style>
