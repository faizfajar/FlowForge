<script setup lang="ts">
import { computed, ref } from 'vue';
import { api } from '../../lib/axios';
import type { ApiResponse } from '../../types/api.types';
import type { WorkflowDag } from '../../types/workflow.types';
import DagVisualizer from '../workflow/DagVisualizer.vue';

type ConfidenceLevel = 'high' | 'medium' | 'low';

const emit = defineEmits<{
    use: [dag: WorkflowDag];
    close: [];
}>();

const prompt = ref('');
const loading = ref(false);
const error = ref<string | null>(null);
const generatedDag = ref<WorkflowDag | null>(null);
const confidence = ref<ConfidenceLevel | null>(null);

const promptIdeas = [
    'Review incoming orders, flag high-value transactions, then notify finance.',
    'Fetch customer tickets, classify urgent issues, then assign to on-call support.',
];

const remaining = computed(() => 400 - prompt.value.length);
const promptProgress = computed(() => Math.min(100, Math.max(0, (prompt.value.length / 400) * 100)));
const canGenerate = computed(() => !loading.value && prompt.value.trim().length > 0);
const generatedStepCount = computed(() => generatedDag.value?.steps.length ?? 0);
const confidenceLabel = computed(() => {
    if (confidence.value === null) {
        return 'Pending';
    }

    return confidence.value.charAt(0).toUpperCase() + confidence.value.slice(1);
});

const applyPromptIdea = (value: string): void => {
    prompt.value = value;
    error.value = null;
};

const resetOutputState = (): void => {
    error.value = null;
    generatedDag.value = null;
    confidence.value = null;
};

const generate = async (): Promise<void> => {
    resetOutputState();
    loading.value = true;

    try {
        const response = await api.post<ApiResponse<{ definition: WorkflowDag; confidence: ConfidenceLevel }>>(
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
            <header class="panel-header">
                <div>
                    <p class="eyebrow">AI workflow builder</p>
                    <h2>Generate a draft workflow</h2>
                    <p class="subtext">Describe the business flow, then review the generated DAG before applying it.</p>
                </div>
                <button type="button" class="icon-button" aria-label="Close AI workflow builder" @click="emit('close')">×</button>
            </header>

            <section class="panel-body">
                <div class="composer">
                    <div class="composer-card">
                        <label class="field">
                            <span>Prompt</span>
                            <textarea
                                v-model="prompt"
                                maxlength="400"
                                placeholder="Example: Review incoming orders, wait for manual approval when amount is above threshold, then send a webhook confirmation.&#10;&#10;Example: Fetch customer tickets, classify urgent issues, then assign them to on-call support."
                                @input="error = null"
                            />
                        </label>

                        <div class="meter">
                            <div class="meter-bar">
                                <span class="meter-fill" :style="{ width: `${promptProgress}%` }"></span>
                            </div>
                            <small :class="{ warning: remaining <= 40 }">{{ remaining }} characters remaining</small>
                        </div>

                        <div class="quick-fill">
                            <span>Quick fill</span>
                            <button
                                v-for="idea in promptIdeas"
                                :key="idea"
                                type="button"
                                class="secondary-button quick-fill-button"
                                @click="applyPromptIdea(idea)"
                            >
                                {{ idea === promptIdeas[0] ? 'Order review flow' : 'Support triage flow' }}
                            </button>
                        </div>

                        <p v-if="error" class="error-banner">{{ error }}</p>

                        <div class="composer-actions">
                            <button type="button" class="secondary-button" @click="resetOutputState">Clear</button>
                            <button type="button" class="primary-button" :disabled="!canGenerate" @click="generate">
                                <span v-if="loading" class="spinner"></span>
                                <span>{{ loading ? 'Generating workflow...' : 'Generate workflow' }}</span>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="preview">
                    <div class="preview-card">
                        <div class="preview-header">
                            <div>
                                <p class="eyebrow">Preview</p>
                                <h3>Generated DAG</h3>
                            </div>
                            <div class="preview-meta">
                                <span class="meta-chip">{{ generatedStepCount }} step{{ generatedStepCount === 1 ? '' : 's' }}</span>
                                <span v-if="confidence !== null" class="confidence" :class="confidence">{{ confidenceLabel }}</span>
                            </div>
                        </div>

                        <div v-if="loading" class="preview-loading" aria-live="polite">
                            <div class="loading-grid">
                                <span class="loading-node"></span>
                                <span class="loading-node"></span>
                                <span class="loading-node"></span>
                            </div>
                            <p>Assembling workflow structure and validating DAG rules.</p>
                        </div>

                        <div v-else-if="generatedDag !== null" class="preview-content">
                            <DagVisualizer :dag="generatedDag" />
                            <div class="preview-actions">
                                <button type="button" class="secondary-button" @click="generate">Try again</button>
                                <button type="button" class="primary-button" @click="emit('use', generatedDag)">Use this workflow</button>
                            </div>
                        </div>

                        <div v-else class="preview-empty">
                            <strong>No draft generated yet</strong>
                            <p>Use the prompt panel to generate a workflow draft with a visual DAG preview.</p>
                        </div>
                    </div>
                </div>
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
    padding: 16px;
}

.panel {
    display: grid;
    grid-template-rows: auto minmax(0, 1fr);
    width: min(1080px, 100%);
    max-height: min(92dvh, 980px);
    overflow: hidden;
    border: 1px solid #dbe3ef;
    border-radius: 8px;
    background: #ffffff;
    box-shadow: 0 24px 64px rgb(15 23 42 / 18%);
}

.panel-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    padding: 20px 22px 16px;
    border-bottom: 1px solid #dbe3ef;
    background: linear-gradient(180deg, #fcfffe 0%, #f7fafc 100%);
}

.eyebrow {
    margin: 0 0 6px;
    color: #0f766e;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
}

h2,
h3 {
    margin: 0;
    color: #172033;
}

.subtext {
    margin: 8px 0 0;
    color: #64748b;
    font-size: 14px;
}

.icon-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border: 1px solid #dbe3ef;
    border-radius: 6px;
    background: #ffffff;
    color: #334155;
    font-size: 24px;
    line-height: 1;
    cursor: pointer;
}

.panel-body {
    display: grid;
    grid-template-columns: minmax(0, 1.05fr) minmax(0, 1fr);
    min-height: 0;
}

.composer,
.preview {
    min-height: 0;
    overflow: auto;
}

.composer {
    padding: 20px;
    border-right: 1px solid #dbe3ef;
    background: #f7fafc;
}

.preview {
    padding: 20px;
    background: #fbfdff;
}

.composer-card,
.preview-card {
    display: grid;
    align-content: start;
    gap: 16px;
    min-height: 100%;
    border: 1px solid #dbe3ef;
    border-radius: 8px;
    background: #ffffff;
    padding: 18px;
}

.field {
    display: grid;
    gap: 8px;
    font-weight: 700;
    color: #172033;
}

textarea {
    width: 100%;
    min-height: 180px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    background: #ffffff;
    padding: 14px;
    color: #172033;
    resize: vertical;
}

.meter {
    display: grid;
    gap: 8px;
}

.meter-bar {
    overflow: hidden;
    height: 8px;
    border-radius: 999px;
    background: #e2e8f0;
}

.meter-fill {
    display: block;
    height: 100%;
    border-radius: inherit;
    background: #0f766e;
    transition: width 0.2s ease;
}

small {
    color: #64748b;
    font-size: 12px;
}

small.warning {
    color: #b45309;
}

.quick-fill {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
}

.quick-fill span {
    color: #64748b;
    font-size: 12px;
    font-weight: 700;
}

.error-banner {
    margin: 0;
    border: 1px solid #fecaca;
    border-radius: 8px;
    background: #fef2f2;
    padding: 12px;
    color: #b91c1c;
    font-size: 13px;
}

.composer-actions,
.preview-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.primary-button,
.secondary-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 40px;
    border-radius: 6px;
    padding: 9px 14px;
    font-weight: 700;
    cursor: pointer;
}

.primary-button {
    border: 1px solid #0f766e;
    background: #0f766e;
    color: #ffffff;
}

.primary-button:disabled {
    opacity: 0.7;
    cursor: wait;
}

.secondary-button {
    border: 1px solid #dbe3ef;
    background: #f8fafc;
    color: #172033;
}

.quick-fill-button {
    min-height: 34px;
    padding: 7px 12px;
    font-size: 12px;
    font-weight: 600;
}

.spinner {
    width: 14px;
    height: 14px;
    border: 2px solid rgb(255 255 255 / 40%);
    border-top-color: #ffffff;
    border-radius: 999px;
    animation: spin 0.8s linear infinite;
}

.preview-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
}

.preview-meta {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 8px;
}

.meta-chip,
.confidence {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 28px;
    border-radius: 999px;
    padding: 4px 10px;
    font-size: 12px;
    font-weight: 700;
}

.meta-chip {
    background: #e2e8f0;
    color: #334155;
}

.confidence.high {
    background: #dcfce7;
    color: #15803d;
}

.confidence.medium {
    background: #fef3c7;
    color: #a16207;
}

.confidence.low {
    background: #fee2e2;
    color: #b91c1c;
}

.preview-loading,
.preview-empty {
    display: grid;
    place-items: center;
    align-content: center;
    gap: 14px;
    min-height: 420px;
    border: 1px dashed #dbe3ef;
    border-radius: 8px;
    background: #f8fafc;
    padding: 24px;
    text-align: center;
    color: #475569;
}

.preview-empty strong {
    color: #172033;
    font-size: 16px;
}

.loading-grid {
    display: flex;
    gap: 12px;
}

.loading-node {
    width: 88px;
    height: 52px;
    border-radius: 8px;
    background: linear-gradient(90deg, #e2e8f0 25%, #f8fafc 50%, #e2e8f0 75%);
    background-size: 200% 100%;
    animation: shimmer 1.2s linear infinite;
}

.preview-content {
    display: grid;
    gap: 14px;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

@keyframes shimmer {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}

@media (max-width: 960px) {
    .panel-body {
        grid-template-columns: 1fr;
    }

    .composer {
        border-right: 0;
        border-bottom: 1px solid #dbe3ef;
    }
}

@media (max-width: 640px) {
    .ai-modal {
        padding: 10px;
    }

    .panel {
        max-height: calc(100dvh - 20px);
    }

    .panel-header,
    .composer,
    .preview {
        padding: 14px;
    }

    .composer-card,
    .preview-card {
        padding: 14px;
    }

    .preview-header,
    .panel-header {
        flex-direction: column;
    }

    .icon-button,
    .composer-actions button,
    .preview-actions button,
    .quick-fill-button {
        width: 100%;
    }

    .preview-meta {
        justify-content: flex-start;
    }
}
</style>
