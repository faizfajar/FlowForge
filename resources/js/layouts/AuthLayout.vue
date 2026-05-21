<template>
    <main class="auth-layout">
        <section class="auth-panel">
            <RouterView />
        </section>
    </main>
</template>

<style scoped>
.auth-layout {
    display: grid;
    min-height: 100vh;
    place-items: center;
    background: #f4f7fb;
    padding: 24px;
}

.auth-panel {
    width: min(440px, 100%);
    background: #ffffff;
    border: 1px solid #d9e2ec;
    border-radius: 8px;
    padding: 28px;
    box-shadow: 0 12px 30px rgb(15 23 42 / 8%);
}
</style>
