import { createApp } from 'vue';
import { createAppI18n, DEFAULT_LOCALE, isSupportedLocale, readLocaleCookie, type SupportedLocale } from '../i18n';
import App from './App.vue';
import '../../css/app.css';

function tgLocaleFromTelegram(): SupportedLocale | null {
    const raw = (window as unknown as {
        Telegram?: { WebApp?: { initDataUnsafe?: { user?: { language_code?: string } } } };
    }).Telegram?.WebApp?.initDataUnsafe?.user?.language_code?.toLowerCase() ?? null;

    if (!raw) {
        return null;
    }

    if (raw === 'ru' || raw.startsWith('ru-')) {
        return 'ru';
    }

    if (raw === 'uz' || raw.startsWith('uz-')) {
        return 'uz';
    }

    if (raw === 'en' || raw.startsWith('en-')) {
        return 'en';
    }

    return null;
}

const root = document.getElementById('miniapp');
const cookieLocale = readLocaleCookie();
const tgLocale = tgLocaleFromTelegram();
const datasetLocale = root?.dataset.locale;

const initialLocale =
    (isSupportedLocale(cookieLocale) && cookieLocale) ||
    tgLocale ||
    (isSupportedLocale(datasetLocale) && datasetLocale) ||
    DEFAULT_LOCALE;

const app = createApp(App);
app.use(createAppI18n(initialLocale));
app.mount('#miniapp');
