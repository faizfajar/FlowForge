<script setup lang="ts">
withDefaults(defineProps<{
    title: string;
    message: string;
    confirmLabel?: string;
    danger?: boolean;
}>(), {
    confirmLabel: 'Confirm',
    danger: false,
});

defineEmits<{
    confirm: [];
    cancel: [];
}>();
</script>

<template>
    <div class="backdrop" role="presentation">
        <section class="dialog" role="dialog" aria-modal="true" :aria-label="title">
            <h2>{{ title }}</h2>
            <p>{{ message }}</p>
            <footer>
                <button type="button" class="secondary" @click="$emit('cancel')">Cancel</button>
                <button type="button" :class="{ danger }" @click="$emit('confirm')">{{ confirmLabel }}</button>
            </footer>
        </section>
    </div>
</template>

<style scoped>
.backdrop {
    position: fixed;
    inset: 0;
    display: grid;
    place-items: center;
    background: rgb(15 23 42 / 45%);
    z-index: 50;
}

.dialog {
    width: min(420px, calc(100vw - 32px));
    border-radius: 8px;
    background: #ffffff;
    padding: 22px;
    box-shadow: 0 20px 60px rgb(15 23 42 / 24%);
}

footer {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 24px;
}

button {
    border: 0;
    border-radius: 6px;
    background: #0f766e;
    color: #ffffff;
    padding: 9px 14px;
}

.secondary {
    background: #e2e8f0;
    color: #1e293b;
}

.danger {
    background: #dc2626;
}
</style>
