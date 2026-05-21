<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import ConfirmDialog from '../../components/ui/ConfirmDialog.vue';
import LoadingSkeleton from '../../components/ui/LoadingSkeleton.vue';
import { useAuthStore } from '../../stores/auth';
import { useWorkflowStore } from '../../stores/workflow';
import { UserRole } from '../../types/auth.types';

const router = useRouter();
const auth = useAuthStore();
const workflowStore = useWorkflowStore();
const search = ref('');
const status = ref('');
const confirmDeleteId = ref<string | null>(null);
const canEdit = computed(() => auth.user?.role === UserRole.ADMIN || auth.user?.role === UserRole.EDITOR);
let debounceTimer = 0;

const load = async (cursor: string | null = null): Promise<void> => {
    await workflowStore.fetchWorkflows({ name: search.value || undefined, status: status.value || undefined, cursor });
};

watch([search, status], () => {
    window.clearTimeout(debounceTimer);
    debounceTimer = window.setTimeout(() => void load(), 300);
});

const trigger = async (id: string): Promise<void> => {
    const run = await workflowStore.triggerWorkflow(id);
    await router.push(`/runs/${run.id}`);
};

const deleteWorkflow = async (): Promise<void> => {
    if (confirmDeleteId.value !== null) {
        await workflowStore.deleteWorkflow(confirmDeleteId.value);
        confirmDeleteId.value = null;
    }
};

onMounted(() => void load());
</script>

<template>
    <section class="page">
        <header>
            <h1>Workflows</h1>
            <RouterLink v-if="canEdit" to="/workflows/create">Create workflow</RouterLink>
        </header>
        <div class="filters">
            <input v-model="search" placeholder="Search workflows" />
            <select v-model="status">
                <option value="">All statuses</option>
                <option value="running">Running</option>
                <option value="failed">Failed</option>
                <option value="completed">Completed</option>
            </select>
        </div>
        <LoadingSkeleton v-if="workflowStore.loading && workflowStore.workflows.length === 0" :rows="3" height="42px" />
        <table v-else>
            <thead><tr><th>Name</th><th>Status</th><th>Last run</th><th>Version</th><th>Created</th><th>Actions</th></tr></thead>
            <tbody>
                <tr v-for="workflow in workflowStore.workflows" :key="workflow.id">
                    <td>{{ workflow.name }}</td>
                    <td>{{ workflow.last_run?.status ?? 'ready' }}</td>
                    <td>{{ workflow.last_run?.started_at ?? '-' }}</td>
                    <td>{{ workflow.active_version.version_number }}</td>
                    <td>{{ workflow.created_at }}</td>
                    <td class="actions">
                        <button v-if="canEdit" type="button" @click="trigger(workflow.id)">Trigger</button>
                        <RouterLink :to="`/workflows/${workflow.id}`">View</RouterLink>
                        <RouterLink v-if="canEdit" :to="`/workflows/${workflow.id}/edit`">Edit</RouterLink>
                        <button v-if="auth.user?.role === UserRole.ADMIN" type="button" @click="confirmDeleteId = workflow.id">Delete</button>
                    </td>
                </tr>
            </tbody>
        </table>
        <button v-if="workflowStore.nextCursor" type="button" @click="load(workflowStore.nextCursor)">Load More</button>
        <ConfirmDialog
            v-if="confirmDeleteId"
            title="Delete workflow"
            message="This workflow will be removed from active lists."
            confirm-label="Delete"
            danger
            @confirm="deleteWorkflow"
            @cancel="confirmDeleteId = null"
        />
    </section>
</template>

<style scoped>
.page { display: grid; gap: 18px; }
header, .filters, .actions { display: flex; gap: 10px; align-items: center; }
header { justify-content: space-between; }
input, select { border: 1px solid #cbd5e1; border-radius: 6px; padding: 9px; }
table { width: 100%; border-collapse: collapse; background: white; }
th, td { border-bottom: 1px solid #e2e8f0; padding: 11px; text-align: left; }
button, a { color: #0f766e; }
</style>
