import { router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { DEFAULT_LOCALE, isSupportedLocale  } from '@/i18n';
import type {SupportedLocale} from '@/i18n';

export type LocaleOption = {
    code: SupportedLocale;
    native: string;
    english: string;
    flag: string;
};

const FALLBACK_LOCALES: LocaleOption[] = [
    { code: 'uz', native: "O'zbekcha", english: 'Uzbek (Latin)', flag: '🇺🇿' },
    { code: 'uz-Cyrl', native: 'Ўзбекча', english: 'Uzbek (Cyrillic)', flag: '🇺🇿' },
    { code: 'ru', native: 'Русский', english: 'Russian', flag: '🇷🇺' },
    { code: 'en', native: 'English', english: 'English', flag: '🇬🇧' },
];

export function useLocale() {
    const i18n = useI18n();
    const page = usePage();

    const current = computed<SupportedLocale>(() => {
        const fromPage = (page.props as Record<string, unknown>).locale;

        return isSupportedLocale(fromPage) ? fromPage : DEFAULT_LOCALE;
    });

    const locales = computed<LocaleOption[]>(() => {
        const raw = (page.props as Record<string, unknown>).locales;

        if (Array.isArray(raw)) {
            return raw.filter((entry): entry is LocaleOption =>
                typeof entry === 'object'
                && entry !== null
                && isSupportedLocale((entry as LocaleOption).code),
            );
        }

        return FALLBACK_LOCALES;
    });

    function switchTo(code: SupportedLocale): void {
        if (! isSupportedLocale(code) || code === current.value) {
            return;
        }

        router.post(`/locale/${code}`, {}, {
            preserveScroll: true,
            preserveState: false,
            onSuccess: () => {
                i18n.locale.value = code;
            },
        });
    }

    return { current, locales, switchTo };
}
