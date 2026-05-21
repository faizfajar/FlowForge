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
const workflowId = computed(() => String(route.params.id));
const canTrigger = computed(() => auth.user?.role === UserRole.ADMIN || auth.user?.role === UserRole.EDITOR);

const trigger = async (): Promise<void> => {
    const run = await store.triggerWorkflow(workflowId.value);
    await router.push(`/runs/${run.id}`);
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
    const response = await api.post<ApiResponse<{ url: string; secret: string }>>(`/api/v1/workflows/${workflowId.value}/webhook`);
    webhook.value = response.data.data;
};

onMounted(async () => {
    await store.fetchWorkflow(workflowId.value);
    await Promise.all([loadRuns(), loadVersions()]);
});
</script>

<template>
    <section v-if="store.currentWorkflow" class="page">
        <header>
            <div>
                <h1>{{ store.currentWorkflow.name }}</h1>
                <span>{{ store.currentWorkflow.last_run?.status ?? 'ready' }}</span>
            </div>
            <button v-if="canTrigger" type="button" @click="trigger">Trigger</button>
        </header>
        <nav class="tabs">
            <button type="button" @click="tab = 'overview'">Overview</button>
            <button type="button" @click="tab = 'history'">Run History</button>
            <button type="button" @click="tab = 'versions'">Versions</button>
        </nav>
        <section v-if="tab === 'overview'" class="panel">
            <p>{{ store.currentWorkflow.description ?? 'No description' }}</p>
            <p>Schedule: {{ store.currentWorkflow.schedule_cron ?? 'manual only' }}</p>
            <button v-if="canTrigger" type="button" @click="ensureWebhook">Create webhook trigger</button>
            <section v-if="webhook" class="webhook">
                <strong>{{ webhook.url }}</strong>
                <span>Secret: {{ webhook.secret }}</span>
            </section>
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
header, .tabs { display: flex; justify-content: space-between; gap: 10px; }
.tabs { justify-content: flex-start; }
.panel, .list, .webhook { display: grid; gap: 10px; }
.version { display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #e2e8f0; padding: 10px 0; }
</style>
