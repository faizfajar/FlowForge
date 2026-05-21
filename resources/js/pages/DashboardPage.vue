<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { api } from '../lib/axios';
import { formatDateTime, formatDurationMs } from '../lib/datetime';
import type { ApiResponse } from '../types/api.types';

interface DashboardStats {
    active_runs_count: number;
    success_rate_last_24h: number;
    average_execution_time_last_24h: number;
    total_runs_today: number;
    runs_per_hour: { hour: string; total: number }[];
    recent_failed_runs: { id: string; workflow_name: string; failed_at: string | null }[];
}

const stats = ref<DashboardStats | null>(null);
const timer = ref<number | null>(null);

const fetchStats = async (): Promise<void> => {
    const response = await api.get<ApiResponse<DashboardStats>>('/api/v1/dashboard/stats');
    stats.value = response.data.data;
};

onMounted(async () => {
    await fetchStats();
    timer.value = window.setInterval(fetchStats, 30000);
});

onUnmounted(() => {
    if (timer.value !== null) window.clearInterval(timer.value);
});
</script>

<template>
    <section class="page">
        <h1>Dashboard</h1>
        <div v-if="stats" class="stats">
            <RouterLink to="/runs?status=RUNNING" class="stat"><span>Active runs</span><strong>{{ stats.active_runs_count }}</strong></RouterLink>
            <article class="stat"><span>Success rate 24h</span><strong>{{ stats.success_rate_last_24h }}%</strong></article>
            <article class="stat"><span>Avg execution 24h</span><strong>{{ formatDurationMs(stats.average_execution_time_last_24h * 1000) }}</strong></article>
            <article class="stat"><span>Total today</span><strong>{{ stats.total_runs_today }}</strong></article>
        </div>
        <svg v-if="stats" viewBox="0 0 600 180" class="chart" role="img" aria-label="Runs per hour">
            <rect
                v-for="(bar, index) in stats.runs_per_hour"
                :key="bar.hour"
                :x="index * 50 + 12"
                :y="170 - Math.min(bar.total * 12, 150)"
                width="28"
                :height="Math.min(bar.total * 12, 150)"
                fill="#0f766e"
            />
        </svg>
        <section v-if="stats">
            <h2>Recent failed runs</h2>
            <RouterLink v-for="run in stats.recent_failed_runs" :key="run.id" :to="`/runs/${run.id}`" class="failed-run">
                {{ run.workflow_name }} · {{ formatDateTime(run.failed_at, 'unknown') }}
            </RouterLink>
        </section>
    </section>
</template>

<style scoped>
.page { display: grid; gap: 22px; }
.stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; }
.stat { display: grid; gap: 8px; border: 1px solid #dbe3ef; border-radius: 8px; background: white; padding: 16px; color: inherit; text-decoration: none; }
.stat strong { font-size: 28px; }
.chart { width: 100%; height: 220px; border: 1px solid #dbe3ef; border-radius: 8px; background: white; }
.failed-run { display: block; padding: 10px 0; color: #b91c1c; }
</style>
