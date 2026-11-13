import '../css/app.css';
import { createApp, Fragment, h } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createPinia } from 'pinia';
import GlobalLoadingOverlay from './Components/GlobalLoadingOverlay.vue';
import { beginGlobalRequest, isGlobalLoading } from './utils/globalLoading';
import { installFormFieldFeedback } from './utils/formFieldFeedback';

installFormFieldFeedback();

const inertiaRequests = new Map();

router.on('start', (event) => {
    if (event.detail.visit.prefetch) return;
    inertiaRequests.set(event.detail.visit, beginGlobalRequest());
});

router.on('finish', (event) => {
    const finish = inertiaRequests.get(event.detail.visit);
    if (!finish) return;
    inertiaRequests.delete(event.detail.visit);
    finish();
});

router.on('navigate', () => {
    window.requestAnimationFrame(() => {
        document.getElementById('main-content')?.focus();
    });
});

createInertiaApp({
    title: (title) => `${title} - OrçaFácil`,
    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        createApp({
            render: () => h(Fragment, null, [
                h(App, props),
                h(GlobalLoadingOverlay, { visible: isGlobalLoading.value }),
            ]),
        }).use(plugin).use(createPinia()).mount(el);
    },
});
