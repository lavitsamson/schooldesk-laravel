import '../css/app.css';

import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import {
    getActiveLanguage,
    i18nVue,
    loadLanguageAsync,
} from 'laravel-vue-i18n';
import { createApp, DefineComponent, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';

const appName = import.meta.env.VITE_APP_NAME || 'SchoolDesk';

// Keys are the English strings (as with Laravel's __()); a missing key shows the English text.
// Bundled eagerly: three small files, and no flash of English before the language loads.
const translations = import.meta.glob<{ default: Record<string, string> }>(
    '../../lang/*.json',
    { eager: true },
);

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .use(i18nVue, {
                lang: props.initialPage.props.locale as string,
                fallbackLang: 'en',
                resolve: (lang: string) =>
                    translations[`../../lang/${lang}.json`]?.default ?? {},
            })
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});

// The language can change without a full page load (profile, school switch).
router.on('navigate', (event) => {
    const locale = event.detail.page.props.locale as string;

    if (locale !== getActiveLanguage()) {
        void loadLanguageAsync(locale);
        document.documentElement.lang = locale;
    }
});
