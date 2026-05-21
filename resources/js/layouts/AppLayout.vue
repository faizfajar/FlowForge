<script setup lang="ts">
import { RouterLink, RouterView } from 'vue-router';
import { useAuthStore } from '../stores/auth';

const auth = useAuthStore();
</script>

<template>
    <div class="app-layout">
        <header class="topbar">
            <RouterLink to="/workflows" class="brand">FlowForge</RouterLink>
            <div class="topbar-actions">
                <RouterLink to="/workflows" class="nav-link">Monitoring</RouterLink>
                <button type="button" @click="auth.logout">Logout</button>
            </div>
        </header>
        <main class="content">
            <RouterView />
        </main>
    </div>
</template>

<style scoped>
.app-layout {
    display: grid;
    grid-template-rows: auto 1fr;
    height: 100dvh;
    background: #f7fafc;
    color: #172033;
}

.topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    border-bottom: 1px solid #dbe3ef;
    background: #ffffff;
    padding: 12px 16px;
}

.brand {
    color: #172033;
    font-size: 16px;
    font-weight: 800;
    text-decoration: none;
}

.topbar-actions {
    display: flex;
    align-items: center;
    gap: 12px;
}

.nav-link {
    color: #334155;
    text-decoration: none;
}

.nav-link.router-link-active {
    color: #0f766e;
    font-weight: 700;
}

.content {
    min-height: 0;
    min-width: 0;
    padding: 18px;
}

.content:has(.monitoring-shell) {
    padding: 0;
    overflow: hidden;
}

@media (max-width: 900px) {
    .topbar {
        position: sticky;
        top: 0;
        z-index: 10;
        align-items: flex-start;
        flex-direction: column;
        padding: 12px 14px;
    }

    .topbar-actions {
        width: 100%;
        justify-content: space-between;
        flex: 1;
    }

    .content:has(.monitoring-shell) .monitoring-shell {
        height: calc(100dvh - 95px);
    }
}
</style>
