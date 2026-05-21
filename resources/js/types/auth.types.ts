export enum UserRole {
    ADMIN = 'ADMIN',
    EDITOR = 'EDITOR',
    VIEWER = 'VIEWER',
}

export interface User {
    id: string;
    name: string;
    email: string;
    role: UserRole;
    tenant: { id: string; name: string };
}

export interface AuthTokens {
    token: string;
    refresh_token: string;
    token_type?: string;
    expires_in?: number;
}

export interface LoginCredentials {
    email: string;
    password: string;
}

export interface RegisterPayload {
    name: string;
    tenant_name: string;
    email: string;
    password: string;
    password_confirmation: string;
}
