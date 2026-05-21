<script setup lang="ts">
import { ref } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import { useAuthStore } from '../../stores/auth';
import type { ValidationError } from '../../types/api.types';

const auth = useAuthStore();
const router = useRouter();
const form = ref({ name: '', tenant_name: '', email: '', password: '', password_confirmation: '' });
const errors = ref<Record<string, string[]>>({});
const loading = ref(false);

const submit = async (): Promise<void> => {
    loading.value = true;
    errors.value = {};
    try {
        await auth.register(form.value);
        await router.push('/dashboard');
    } catch (caught: unknown) {
        const validation = caught as Partial<ValidationError>;
        errors.value = validation.errors ?? {};
    } finally {
        loading.value = false;
    }
};
</script>

<template>
    <form class="auth-form" @submit.prevent="submit">
        <h1>Register</h1>
        <label>Name<input v-model="form.name" required /></label>
        <p v-if="errors.name" class="error">{{ errors.name[0] }}</p>
        <label>Tenant name<input v-model="form.tenant_name" required /></label>
        <p v-if="errors.tenant_name" class="error">{{ errors.tenant_name[0] }}</p>
        <label>Email<input v-model="form.email" required type="email" /></label>
        <p v-if="errors.email" class="error">{{ errors.email[0] }}</p>
        <label>Password<input v-model="form.password" required minlength="6" type="password" /></label>
        <label>Confirm password<input v-model="form.password_confirmation" required minlength="6" type="password" /></label>
        <p v-if="errors.password" class="error">{{ errors.password[0] }}</p>
        <button type="submit" :disabled="loading">{{ loading ? 'Creating...' : 'Register' }}</button>
        <RouterLink to="/login">Back to login</RouterLink>
    </form>
</template>

<style scoped>
.auth-form { display: grid; gap: 13px; }
label { display: grid; gap: 6px; font-weight: 700; }
input { border: 1px solid #cbd5e1; border-radius: 6px; padding: 10px; }
button { border: 0; border-radius: 6px; background: #0f766e; color: white; padding: 11px; }
.error { color: #b91c1c; margin: 0; }
</style>
