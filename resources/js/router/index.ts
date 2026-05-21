import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router';
import AuthLayout from '../layouts/AuthLayout.vue';
import AppLayout from '../layouts/AppLayout.vue';
import LoginPage from '../pages/auth/LoginPage.vue';
import RegisterPage from '../pages/auth/RegisterPage.vue';
import WorkflowListPage from '../pages/workflows/WorkflowListPage.vue';
import WorkflowEditorPage from '../pages/workflows/WorkflowEditorPage.vue';
import WorkflowDetailPage from '../pages/workflows/WorkflowDetailPage.vue';
import RunDetailPage from '../pages/runs/RunDetailPage.vue';
import { useAuthStore } from '../stores/auth';
import { UserRole } from '../types/auth.types';

const routes: RouteRecordRaw[] = [
    {
        path: '/',
        component: AppLayout,
        children: [
            { path: '', redirect: '/workflows' },
            { path: 'dashboard', redirect: '/workflows', meta: { requiresAuth: true } },
            { path: 'workflows', component: WorkflowListPage, meta: { requiresAuth: true } },
            {
                path: 'workflows/create',
                redirect: '/workflows',
                meta: { requiresAuth: true },
            },
            { path: 'workflows/:id', component: WorkflowDetailPage, meta: { requiresAuth: true } },
            {
                path: 'workflows/:id/edit',
                component: WorkflowEditorPage,
                meta: { requiresAuth: true, roles: [UserRole.ADMIN, UserRole.EDITOR] },
            },
            { path: 'runs/:runId', component: RunDetailPage, meta: { requiresAuth: true } },
        ],
    },
    {
        path: '/',
        component: AuthLayout,
        children: [
            { path: 'login', component: LoginPage },
            { path: 'register', component: RegisterPage },
        ],
    },
];

export const router = createRouter({
    history: createWebHistory(),
    routes,
});

router.beforeEach(async (to) => {
    const auth = useAuthStore();

    if (!auth.initialized) {
        await auth.initialize();
    }

    if (to.meta.requiresAuth && !auth.isAuthenticated) {
        return '/login';
    }

    const roles = to.meta.roles as UserRole[] | undefined;
    if (roles !== undefined && auth.user !== null && !roles.includes(auth.user.role)) {
        return auth.user.role === UserRole.ADMIN ? '/dashboard' : '/workflows';
    }

    return true;
});
