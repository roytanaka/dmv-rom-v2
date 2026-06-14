import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { i18nVue } from 'laravel-vue-i18n';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';

// Extend Vite's ImportMetaEnv with app-specific keys.
declare global {
    interface ImportMetaEnv {
        readonly VITE_APP_NAME: string;
    }
}

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./pages/${name}.vue`, import.meta.glob<DefineComponent>('./pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        // Boot the i18n bridge in the locale resolved server-side from the URL
        // (shared as an Inertia prop), so chrome renders correctly on first paint.
        const locale = (props.initialPage.props.locale as string) ?? 'en';

        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .use(i18nVue, {
                lang: locale,
                resolve: async (lang: string) => {
                    const langs = import.meta.glob('../../lang/*.json');
                    return await langs[`../../lang/php_${lang}.json`]();
                },
            })
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
