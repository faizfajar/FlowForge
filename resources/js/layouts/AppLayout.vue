<script setup lang="ts">
import { RouterLink, RouterView } from 'vue-router';
import { useAuthStore } from '../stores/auth';

const auth = useAuthStore();
</script>

<template>
    <div class="app-layout">
        <aside class="sidebar">
            <strong>FlowForge</strong>
            <nav>
                <RouterLink to="/dashboard">Dashboard</RouterLink>
                <RouterLink to="/workflows">Workflows</RouterLink>
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
    min-width: 0;
}

.content:has(.monitoring-page) {
    padding: 0;
    overflow: hidden;
}

@media (max-width: 900px) {
    .app-layout {
        grid-template-columns: 1fr;
        grid-template-rows: auto 1fr;
    }

    .sidebar {
        position: sticky;
        top: 0;
        z-index: 10;
        flex-direction: row;
        align-items: center;
        gap: 16px;
        padding: 12px 14px;
    }

    .sidebar nav {
        display: flex;
        flex: 1;
        gap: 12px;
        overflow-x: auto;
    }

    .sidebar button {
        margin-top: 0;
        white-space: nowrap;
    }

    .content:has(.monitoring-page) .monitoring-page,
    .content:has(.monitoring-page) .workflow-panel,
    .content:has(.monitoring-page) .run-panel,
    .content:has(.monitoring-page) .trace-panel,
    .content:has(.monitoring-page) .page.embedded,
    .content:has(.monitoring-page) .empty-panel {
        height: calc(100dvh - 57px);
    }
}
</style>
