import { createInertiaApp, type DefineComponent } from '@inertiajs/vue3';
import createServer from '@inertiajs/vue3/server';
import { renderToString } from '@vue/server-renderer';
import { createSSRApp, h } from 'vue';
import { createAppI18n, DEFAULT_LOCALE } from '@/i18n';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createServer((page) =>
    createInertiaApp({
        page,
        render: renderToString,
        title: (title) => (title ? `${title} - ${appName}` : appName),
        resolve: (name) => {
            const pages = import.meta.glob<DefineComponent>('./pages/**/*.vue');
            const importer = pages[`./pages/${name}.vue`];

            if (! importer) {
                throw new Error(`Page not found: ${name}`);
            }

            return importer();
        },
        setup({ App, props, plugin }) {
            const app = createSSRApp({ render: () => h(App, props) });
            const locale = (props.initialPage.props as Record<string, unknown>).locale as string | undefined;
            const i18n = createAppI18n(locale ?? DEFAULT_LOCALE);

            app.use(plugin);
            app.use(i18n);

            return app;
        },
    }),
);
