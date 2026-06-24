import { createInertiaApp, router } from '@inertiajs/vue3';
import { initializeTheme } from '@/composables/useAppearance';
import { createAppI18n, isSupportedLocale, DEFAULT_LOCALE, readLocaleCookie  } from '@/i18n';
import type {SupportedLocale} from '@/i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { initializeFlashToast } from '@/lib/flashToast';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

const i18n = createAppI18n(readLocaleCookie() ?? DEFAULT_LOCALE);

// Eslatma: valyuta belgisi (currency) endi app.ts'da emas, AppSidebarLayout'da
// `currency` shared prop'idan reaktiv sync qilinadi — yagona manba.
router.on('success', (event) => {
    const props = event.detail.page.props as Record<string, unknown>;
    const incoming = props.locale;

    if (isSupportedLocale(incoming) && i18n.global.locale.value !== incoming) {
        i18n.global.locale.value = incoming as SupportedLocale;
    }
});

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'Welcome':
                return null;
            case name.startsWith('Blog/'):
            case name.startsWith('Compare/'):
            case name.startsWith('Pricing/'):
                return null;
            case name === 'auth/Register':
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    progress: {
        color: '#4B5563',
    },
    withApp: (app) => {
        app.use(i18n);
    },
});

// This will set light / dark mode on page load...
initializeTheme();

// This will listen for flash toast data from the server...
initializeFlashToast();
