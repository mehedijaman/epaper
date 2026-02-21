import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import '../css/app.css';
import { initializeTheme } from './composables/useAppearance';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';
const blockedByClientAliases: Record<string, string> = {
    'EpAdmin/Ads/Index': 'EpAdmin/Placements/Index',
};
const lazyPages = import.meta.glob<DefineComponent>([
    './pages/**/*.vue',
    '!./pages/EpAdmin/Ads/**/*.vue',
]);
const eagerPages = import.meta.glob<DefineComponent>([
    './pages/**/*.vue',
    '!./pages/EpAdmin/Ads/**/*.vue',
], { eager: true });

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) => {
        const resolvedName = blockedByClientAliases[name] ?? name;
        const pagePath = `./pages/${resolvedName}.vue`;

        if (import.meta.env.DEV) {
            const page = eagerPages[pagePath];

            if (page === undefined) {
                throw new Error(`Page not found: ${pagePath}`);
            }

            return Promise.resolve(page);
        }

        return resolvePageComponent(pagePath, lazyPages);
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();
