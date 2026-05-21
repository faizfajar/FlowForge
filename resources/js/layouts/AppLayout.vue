<script setup lang="ts">
import { computed } from 'vue';
import { RouterLink, RouterView } from 'vue-router';
import { UserRole } from '../types/auth.types';
import { useAuthStore } from '../stores/auth';

const auth = useAuthStore();
const canEdit = computed(() => auth.user?.role === UserRole.ADMIN || auth.user?.role === UserRole.EDITOR);
</script>

<template>
    <div class="app-layout">
        <aside class="sidebar">
            <strong>FlowForge</strong>
            <nav>
                <RouterLink to="/dashboard">Dashboard</RouterLink>
                <RouterLink to="/workflows">Workflows</RouterLink>
                <RouterLink v-if="canEdit" to="/workflows/create">Create</RouterLink>
            </nav>
            <button type="button" @click="auth.logout">Logout</button>
        </aside>
        <main class="content">
            <RouterView />
        </main>
    </div>
</template>

<style scoped>
.app-layout {
    display: grid;
    grid-template-columns: 220px 1fr;
    min-height: 100vh;
    background: #f7fafc;
    color: #172033;
}

.sidebar {
    display: flex;
    flex-direction: column;
    gap: 24px;
    border-right: 1px solid #dbe3ef;
    background: #ffffff;
    padding: 24px;
}

.sidebar nav {
    display: grid;
    gap: 10px;
}

.sidebar a {
    color: #334155;
    text-decoration: none;
}

.sidebar a.router-link-active {
    color: #0f766e;
    font-weight: 700;
}

.sidebar button {
    margin-top: auto;
}

.content {
    padding: 28px;
}
</style>
