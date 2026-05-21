<script setup lang="ts">
import { ref } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import { useAuthStore } from '../../stores/auth';
import type { ValidationError } from '../../types/api.types';

const auth = useAuthStore();
const router = useRouter();
const email = ref('');
const password = ref('');
const loading = ref(false);
const errors = ref<Record<string, string[]>>({});

const submit = async (): Promise<void> => {
    loading.value = true;
    errors.value = {};
    try {
        await auth.login({ email: email.value, password: password.value });
        await router.push('/dashboard');
    } catch (caught: unknown) {
        const validation = caught as Partial<ValidationError>;
        errors.value = validation.errors ?? { email: [caught instanceof Error ? caught.message : 'Login failed.'] };
    } finally {
        loading.value = false;
    }
};
</script>

<template>
    <form class="auth-form" @submit.prevent="submit">
        <h1>Login</h1>
        <label>Email<input v-model="email" required type="email" /></label>
        <p v-if="errors.email" class="error">{{ errors.email[0] }}</p>
        <label>Password<input v-model="password" required minlength="6" type="password" /></label>
        <p v-if="errors.password" class="error">{{ errors.password[0] }}</p>
        <button type="submit" :disabled="loading">{{ loading ? 'Signing in...' : 'Login' }}</button>
        <RouterLink to="/register">Create account</RouterLink>
    </form>
</template>

<style scoped>
.auth-form { display: grid; gap: 14px; }
label { display: grid; gap: 6px; font-weight: 700; }
input { border: 1px solid #cbd5e1; border-radius: 6px; padding: 10px; }
button { border: 0; border-radius: 6px; background: #0f766e; color: white; padding: 11px; }
.error { color: #b91c1c; margin: 0; }
</style>
