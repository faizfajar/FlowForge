import { readonly, ref } from 'vue';

type ToastType = 'success' | 'error' | 'warning';

interface Toast {
    id: number;
    type: ToastType;
    message: string;
}

const toasts = ref<Toast[]>([]);
let nextId = 1;

const push = (type: ToastType, message: string): void => {
    const id = nextId;
    nextId += 1;
    toasts.value = [...toasts.value, { id, type, message }];

    window.setTimeout(() => {
        toasts.value = toasts.value.filter((toast) => toast.id !== id);
    }, 4000);
};

export function useToast() {
    return {
        toasts: readonly(toasts),
        success: (message: string) => push('success', message),
        error: (message: string) => push('error', message),
        warning: (message: string) => push('warning', message),
    };
}
