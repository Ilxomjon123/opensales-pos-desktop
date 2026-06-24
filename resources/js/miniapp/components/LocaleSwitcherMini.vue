<script setup lang="ts">
import { Languages, Check } from 'lucide-vue-next';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { SUPPORTED_LOCALES  } from '../../i18n';
import type {SupportedLocale} from '../../i18n';

const LOCALE_LABELS: Record<SupportedLocale, { native: string; flag: string }> = {
    'uz': { native: "O'zbekcha", flag: '🇺🇿' },
    'uz-Cyrl': { native: 'Ўзбекча', flag: '🇺🇿' },
    'ru': { native: 'Русский', flag: '🇷🇺' },
    'en': { native: 'English', flag: '🇬🇧' },
};

const { locale } = useI18n();
const open = ref(false);

function selectLocale(code: SupportedLocale): void {
    locale.value = code;

    const oneYearSeconds = 60 * 60 * 24 * 365;

    document.cookie = `locale=${encodeURIComponent(code)}; path=/; max-age=${oneYearSeconds}; samesite=lax`;

    open.value = false;
}
</script>

<template>
    <div class="relative">
        <button
            type="button"
            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-border text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
            aria-label="Language"
            @click="open = !open"
        >
            <Languages class="h-4 w-4" />
        </button>

        <div
            v-if="open"
            class="fixed inset-0 z-40"
            @click="open = false"
        ></div>

        <div
            v-if="open"
            class="absolute right-0 top-full z-50 mt-2 w-48 overflow-hidden rounded-xl border bg-card shadow-lg"
        >
            <button
                v-for="code in SUPPORTED_LOCALES"
                :key="code"
                type="button"
                class="flex w-full items-center justify-between gap-2 px-3 py-2.5 text-left text-sm transition-colors hover:bg-muted"
                @click="selectLocale(code)"
            >
                <span class="flex items-center gap-2">
                    <span aria-hidden="true">{{ LOCALE_LABELS[code].flag }}</span>
                    <span>{{ LOCALE_LABELS[code].native }}</span>
                </span>
                <Check
                    v-if="locale === code"
                    class="h-4 w-4 text-primary"
                />
            </button>
        </div>
    </div>
</template>
