import { computed, shallowRef } from 'vue';

export function usePagination<T>(loader: (cursor: string | null) => Promise<{ data: T[]; nextCursor: string | null }>) {
    const items = shallowRef<T[]>([]);
    const nextCursor = shallowRef<string | null>(null);
    const loading = shallowRef(false);
    const hasMore = computed(() => nextCursor.value !== null || items.value.length === 0);

    const loadMore = async (): Promise<void> => {
        if (loading.value) {
            return;
        }

        loading.value = true;
        try {
            const response = await loader(nextCursor.value);
            items.value = [...items.value, ...response.data];
            nextCursor.value = response.nextCursor;
        } finally {
            loading.value = false;
        }
    };

    return { items, nextCursor, hasMore, loading, loadMore };
}
