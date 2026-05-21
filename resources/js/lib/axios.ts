import axios, { AxiosError, type AxiosInstance, type AxiosRequestConfig } from 'axios';
import type { ApiError, ApiResponse, ValidationError } from '../types/api.types';
import type { AuthTokens } from '../types/auth.types';

const TOKEN_KEY = 'flowforge.access_token';
const REFRESH_TOKEN_KEY = 'flowforge.refresh_token';

interface RetryableRequestConfig extends AxiosRequestConfig {
    _retry?: boolean;
    skipAuthRefresh?: boolean;
}

export const tokenStorage = {
    getToken: (): string | null => window.localStorage.getItem(TOKEN_KEY),
    getRefreshToken: (): string | null => window.localStorage.getItem(REFRESH_TOKEN_KEY),
    setTokens: (tokens: AuthTokens): void => {
        window.localStorage.setItem(TOKEN_KEY, tokens.token);
        window.localStorage.setItem(REFRESH_TOKEN_KEY, tokens.refresh_token);
    },
    clear: (): void => {
        window.localStorage.removeItem(TOKEN_KEY);
        window.localStorage.removeItem(REFRESH_TOKEN_KEY);
    },
};

export const api: AxiosInstance = axios.create({
    baseURL: import.meta.env.VITE_API_URL ?? '',
    headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
});

let refreshRequest: Promise<string> | null = null;

const refreshAccessToken = async (): Promise<string> => {
    const refreshToken = tokenStorage.getRefreshToken();

    if (refreshToken === null) {
        throw new Error('Missing refresh token.');
    }

    refreshRequest ??= api.post<ApiResponse<AuthTokens>>('/api/v1/auth/refresh', {
        refresh_token: refreshToken,
    }, {
        skipAuthRefresh: true,
    } as RetryableRequestConfig).then((response) => {
        tokenStorage.setTokens(response.data.data);
        return response.data.data.token;
    }).finally(() => {
        refreshRequest = null;
    });

    return refreshRequest;
};

const redirectToLogin = (): void => {
    if (window.location.pathname !== '/login') {
        window.location.assign('/login');
    }
};

const normalizeValidationError = (error: AxiosError<ApiError>): ValidationError => {
    const validationError = new Error(error.response?.data.message ?? 'Validation failed.') as ValidationError;
    validationError.status = 422;
    validationError.errors = error.response?.data.errors ?? {};

    return validationError;
};

const isAuthEndpoint = (url?: string): boolean => {
    if (url === undefined) {
        return false;
    }

    return ['/auth/login', '/auth/register', '/auth/refresh'].some((endpoint) => url.includes(endpoint));
};

api.interceptors.request.use((config) => {
    const token = tokenStorage.getToken();

    if (token !== null) {
        config.headers.Authorization = `Bearer ${token}`;
    }

    return config;
});

api.interceptors.response.use(
    (response) => response,
    async (error: AxiosError<ApiError>) => {
        const originalRequest = error.config as RetryableRequestConfig | undefined;

        if (error.response?.status === 422) {
            return Promise.reject(normalizeValidationError(error));
        }

        if (
            error.response?.status !== 401
            || originalRequest === undefined
            || originalRequest._retry
            || originalRequest.skipAuthRefresh
            || isAuthEndpoint(originalRequest.url)
        ) {
            return Promise.reject(error);
        }

        originalRequest._retry = true;

        try {
            originalRequest.headers = {
                ...originalRequest.headers,
                Authorization: `Bearer ${await refreshAccessToken()}`,
            };

            return api(originalRequest);
        } catch (refreshError: unknown) {
            tokenStorage.clear();
            redirectToLogin();

            return Promise.reject(refreshError);
        }
    },
);

export { refreshAccessToken };
