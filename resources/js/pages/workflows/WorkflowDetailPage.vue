<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import DagVisualizer from '../../components/workflow/DagVisualizer.vue';
import { api } from '../../lib/axios';
import { formatDateTime } from '../../lib/datetime';
import { useAuthStore } from '../../stores/auth';
import { useWorkflowStore } from '../../stores/workflow';
import { UserRole } from '../../types/auth.types';
import type { ApiResponse, PaginatedResponse } from '../../types/api.types';
import type { WorkflowVersion } from '../../types/workflow.types';
import type { WorkflowRun } from '../../types/run.types';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const store = useWorkflowStore();
const tab = ref<'overview' | 'history' | 'versions'>('overview');
const runs = ref<WorkflowRun[]>([]);
const versions = ref<WorkflowVersion[]>([]);
const webhook = ref<{ url: string; secret: string } | null>(null);
const webhookLoading = ref(false);
const triggerLoading = ref(false);
const workflowId = computed(() => String(route.params.id));
const canTrigger = computed(() => auth.user?.role === UserRole.ADMIN || auth.user?.role === UserRole.EDITOR);

const trigger = async (): Promise<void> => {
    if (triggerLoading.value) return;
    triggerLoading.value = true;
    try {
        const run = await store.triggerWorkflow(workflowId.value);
        await router.push(`/runs/${run.id}`);
    } finally {
        triggerLoading.value = false;
    }
};

const loadRuns = async (): Promise<void> => {
    const response = await api.get<PaginatedResponse<WorkflowRun>>('/api/v1/runs', {
        params: { workflow_definition_id: workflowId.value },
    });
    runs.value = response.data.data;
};

const loadVersions = async (): Promise<void> => {
    const response = await api.get<PaginatedResponse<WorkflowVersion>>(`/api/v1/workflows/${workflowId.value}/versions`);
    versions.value = response.data.data;
};

const restoreVersion = async (version: number): Promise<void> => {
    await api.post<ApiResponse<unknown>>(`/api/v1/workflows/${workflowId.value}/versions/${version}/restore`);
    await store.fetchWorkflow(workflowId.value);
    await loadVersions();
};

const ensureWebhook = async (): Promise<void> => {
    if (webhookLoading.value) return;
    webhookLoading.value = true;
    try {
        const response = await api.post<ApiResponse<{ url: string; secret: string }>>(`/api/v1/workflows/${workflowId.value}/webhook`);
        webhook.value = response.data.data;
    } finally {
        webhookLoading.value = false;
    }
};

onMounted(async () => {
    await store.fetchWorkflow(workflowId.value);
    await Promise.all([loadRuns(), loadVersions()]);
});
</script>

<template>
    <section v-if="store.currentWorkflow" class="page">
        <header class="detail-header">
            <div>
                <h1>{{ store.currentWorkflow.name }}</h1>
                <span>{{ store.currentWorkflow.last_run?.status ?? 'ready' }}</span>
            </div>
            <div v-if="canTrigger" class="actions">
                <button type="button" :disabled="triggerLoading" @click="trigger">
                    {{ triggerLoading ? 'Triggering...' : 'Trigger run' }}
                </button>
                <button type="button" class="secondary" :disabled="webhookLoading" @click="ensureWebhook">
                    {{ webhookLoading ? 'Preparing...' : 'Webhook trigger' }}
                </button>
            </div>
        </header>

        <section v-if="webhook" class="webhook-card">
            <span>Webhook endpoint</span>
            <strong>{{ webhook.url }}</strong>
            <small>Secret: {{ webhook.secret }}</small>
        </section>

        <nav class="tabs">
            <button type="button" :class="{ active: tab === 'overview' }" @click="tab = 'overview'">Overview</button>
            <button type="button" :class="{ active: tab === 'history' }" @click="tab = 'history'">Run History</button>
            <button type="button" :class="{ active: tab === 'versions' }" @click="tab = 'versions'">Versions</button>
        </nav>

        <section v-if="tab === 'overview'" class="panel">
            <p>{{ store.currentWorkflow.description ?? 'No description' }}</p>
            <p>Schedule: {{ store.currentWorkflow.schedule_cron ?? 'manual only' }}</p>
            <DagVisualizer :dag="store.currentWorkflow.active_version.dag" />
        </section>

        <section v-if="tab === 'history'" class="list">
            <RouterLink v-for="run in runs" :key="run.id" :to="`/runs/${run.id}`">
                {{ run.status }} · {{ run.trigger_type }} · {{ formatDateTime(run.started_at, 'pending') }}
            </RouterLink>
            <p v-if="runs.length === 0">No runs yet.</p>
        </section>

        <section v-if="tab === 'versions'" class="list">
            <article v-for="version in versions" :key="version.id" class="version">
                <span>Version {{ version.version_number }} · {{ version.is_active ? 'active' : 'inactive' }}</span>
                <button v-if="canTrigger && !version.is_active" type="button" @click="restoreVersion(version.version_number)">Restore</button>
            </article>
        </section>
    </section>
</template>

<style scoped>
.page { display: grid; gap: 18px; }
.detail-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
}
h1 { margin: 0; }
.detail-header span { color: #64748b; font-size: 13px; }
.actions, .tabs { display: flex; gap: 10px; }
button {
    border: 1px solid #0f766e;
    border-radius: 6px;
    background: #0f766e;
    padding: 8px 11px;
    color: white;
    font-weight: 700;
    cursor: pointer;
}
button.secondary, .tabs button {
    border-color: #dbe3ef;
    background: white;
    color: #334155;
}
button:disabled { opacity: 0.65; cursor: wait; }
.tabs { justify-content: flex-start; border-bottom: 1px solid #dbe3ef; }
.tabs button { border-bottom: 0; border-radius: 6px 6px 0 0; }
.tabs button.active { border-color: #0f766e; color: #0f766e; }
.panel, .list, .webhook-card { display: grid; gap: 10px; }
.webhook-card {
    border: 1px solid #dbe3ef;
    border-left: 4px solid #0f766e;
    border-radius: 8px;
    background: white;
    padding: 12px;
}
.webhook-card span, .webhook-card small { color: #64748b; font-size: 12px; }
.webhook-card strong {
    overflow-wrap: anywhere;
    color: #172033;
    font-size: 13px;
}
.version {
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid #e2e8f0;
    padding: 10px 0;
}
</style>
