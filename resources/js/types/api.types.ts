export interface ApiResponse<T> {
    data: T;
    message?: string;
}

export interface PaginatedResponse<T> {
    data: T[];
    meta: {
        next_cursor: string | null;
        per_page: number;
    };
}

export interface ApiError {
    message: string;
    errors?: Record<string, string[]>;
}

export interface ValidationError extends Error {
    status: 422;
    errors: Record<string, string[]>;
}
