import { createApp } from 'vue';

const app = createApp({
    template: '<main id="flowforge-app">FlowForge</main>',
});

try {
    app.mount('#app');
} catch (error: unknown) {
    console.error('Unable to mount FlowForge SPA.', error);
}
