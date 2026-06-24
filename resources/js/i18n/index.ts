import { createI18n } from 'vue-i18n';
import en from './locales/en.json';
import ru from './locales/ru.json';
import uzCyrl from './locales/uz-Cyrl.json';
import uz from './locales/uz.json';

export type SupportedLocale = 'uz' | 'uz-Cyrl' | 'ru' | 'en';

export const SUPPORTED_LOCALES: SupportedLocale[] = ['uz', 'uz-Cyrl', 'ru', 'en'];

export const DEFAULT_LOCALE: SupportedLocale = 'uz';

export const messages = {
    uz,
    'uz-Cyrl': uzCyrl,
    ru,
    en,
};

export function isSupportedLocale(value: unknown): value is SupportedLocale {
    return typeof value === 'string' && (SUPPORTED_LOCALES as string[]).includes(value);
}

export function createAppI18n(initialLocale: string | null | undefined) {
    const locale = isSupportedLocale(initialLocale) ? initialLocale : DEFAULT_LOCALE;

    return createI18n({
        legacy: false,
        globalInjection: true,
        locale,
        fallbackLocale: DEFAULT_LOCALE,
        messages,
    });
}

export function readLocaleCookie(): SupportedLocale | null {
    if (typeof document === 'undefined') {
        return null;
    }

    const match = document.cookie.match(/(?:^|;\s*)locale=([^;]+)/);
    const value = match ? decodeURIComponent(match[1]) : null;

    return isSupportedLocale(value) ? value : null;
}
