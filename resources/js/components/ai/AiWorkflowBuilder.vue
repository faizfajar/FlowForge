<script setup lang="ts">
import { computed, ref } from 'vue';
import { api } from '../../lib/axios';
import type { ApiResponse } from '../../types/api.types';
import type { WorkflowDag } from '../../types/workflow.types';
import DagVisualizer from '../workflow/DagVisualizer.vue';

const emit = defineEmits<{
    use: [dag: WorkflowDag];
    close: [];
}>();

const prompt = ref('');
const loading = ref(false);
const error = ref<string | null>(null);
const generatedDag = ref<WorkflowDag | null>(null);
const confidence = ref<'high' | 'medium' | 'low' | null>(null);

const remaining = computed(() => 400 - prompt.value.length);

const generate = async (): Promise<void> => {
    error.value = null;
    generatedDag.value = null;
    confidence.value = null;
    loading.value = true;
    try {
        const response = await api.post<ApiResponse<{ definition: WorkflowDag; confidence: 'high' | 'medium' | 'low' }>>(
            '/api/v1/ai/generate-workflow',
            { prompt: prompt.value },
        );
        generatedDag.value = response.data.data.definition;
        confidence.value = response.data.data.confidence;
    } catch (caught: unknown) {
        error.value = caught instanceof Error ? caught.message : 'Unable to generate workflow.';
    } finally {
        loading.value = false;
    }
};
</script>

<template>
    <div class="ai-modal">
        <section class="panel">
            <header>
                <h2>Generate with AI</h2>
                <button type="button" @click="emit('close')">×</button>
            </header>
            <textarea
                v-model="prompt"
                maxlength="400"
                placeholder="Describe the workflow you want to build"
                @input="error = null"
            />
            <small>{{ remaining }} characters remaining</small>
            <p v-if="error" class="error">{{ error }}</p>
            <button type="button" :disabled="loading || prompt.trim().length === 0" @click="generate">
                {{ loading ? 'Generating...' : 'Generate' }}
            </button>
            <section v-if="generatedDag" class="preview">
                <span v-if="confidence" class="confidence" :class="confidence">{{ confidence }}</span>
                <DagVisualizer :dag="generatedDag" />
                <footer>
                    <button type="button" @click="generate">Try Again</button>
                    <button type="button" @click="emit('use', generatedDag)">Use This Workflow</button>
                </footer>
            </section>
        </section>
    </div>
</template>

<style scoped>
.ai-modal {
    position: fixed;
    inset: 0;
    z-index: 60;
    display: grid;
    place-items: center;
    background: rgb(15 23 42 / 45%);
}

.panel {
    width: min(860px, calc(100vw - 32px));
    max-height: calc(100vh - 32px);
    overflow: auto;
    border-radius: 8px;
    background: #ffffff;
    padding: 20px;
}

header,
footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

textarea {
    width: 100%;
    min-height: 120px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    padding: 12px;
    resize: vertical;
}

.error {
    color: #b91c1c;
}

.confidence {
    display: inline-flex;
    border-radius: 999px;
    padding: 3px 10px;
    font-weight: 700;
}

.high { background: #dcfce7; color: #15803d; }
.medium { background: #fef3c7; color: #a16207; }
.low { background: #fee2e2; color: #b91c1c; }
</style>
