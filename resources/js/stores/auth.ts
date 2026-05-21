import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import { api, tokenStorage } from '../lib/axios';
import type { ApiResponse } from '../types/api.types';
import type { AuthTokens, LoginCredentials, RegisterPayload, User } from '../types/auth.types';

interface AuthPayload extends AuthTokens {
    user: User;
}

export const useAuthStore = defineStore('auth', () => {
    const user = ref<User | null>(null);
    const token = ref<string | null>(tokenStorage.getToken());
    const initialized = ref(false);
    const isAuthenticated = computed(() => token.value !== null && user.value !== null);

    const setSession = (payload: AuthPayload): void => {
        user.value = payload.user;
        token.value = payload.token;
        tokenStorage.setTokens(payload);
    };

    const login = async (credentials: LoginCredentials): Promise<void> => {
        const response = await api.post<ApiResponse<AuthPayload>>('/api/v1/auth/login', credentials);
        setSession(response.data.data);
    };

    const register = async (payload: RegisterPayload): Promise<void> => {
        const response = await api.post<ApiResponse<AuthPayload>>('/api/v1/auth/register', payload);
        setSession(response.data.data);
    };

    const fetchMe = async (): Promise<void> => {
        const response = await api.get<ApiResponse<User>>('/api/v1/auth/me');
        user.value = response.data.data;
    };

    const logout = async (): Promise<void> => {
        try {
            await api.post('/api/v1/auth/logout');
        } finally {
            user.value = null;
            token.value = null;
            tokenStorage.clear();
            window.location.assign('/login');
        }
    };

    const initialize = async (): Promise<void> => {
        token.value = tokenStorage.getToken();

        if (token.value !== null) {
            try {
                await fetchMe();
            } catch {
                tokenStorage.clear();
                token.value = null;
                user.value = null;
            }
        }

        initialized.value = true;
    };

    return { user, token, initialized, isAuthenticated, login, register, logout, fetchMe, initialize };
});
