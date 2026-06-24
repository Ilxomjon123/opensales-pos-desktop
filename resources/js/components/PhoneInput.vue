<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

/**
 * Davlat tanlash bilan telefon input (+998 / +7). Tashqariga TO'LIQ raqamni
 * `+998901234567` ko'rinishida `update:modelValue` orqali chiqaradi. Tanlangan
 * davlatga mos mask + raqam uzunligi.
 */
type Country = {
    code: string;
    flag: string;
    name: string;
    prefix: string;
    digits: number;
    groups: number[]; // probel qo'yiladigan indekslar
    sample: string;
};

const COUNTRIES: Country[] = [
    {
        code: 'uz',
        flag: '🇺🇿',
        name: "O'zbekiston",
        prefix: '+998',
        digits: 9,
        groups: [2, 5, 7],
        sample: '90 123 45 67',
    },
    {
        code: 'ru',
        flag: '🇷🇺',
        name: 'Россия',
        prefix: '+7',
        digits: 10,
        groups: [3, 6, 8],
        sample: '912 345 67 89',
    },
];

const props = withDefaults(
    defineProps<{
        modelValue: string | null;
        disabled?: boolean;
        error?: boolean;
    }>(),
    { disabled: false, error: false },
);

const emit = defineEmits<{ (e: 'update:modelValue', value: string): void }>();

// Default country — diller davlati (Inertia `country` shared prop). Topilmasa — birinchisi.
const page = usePage();
const dealerCountryCode = (page.props.country as { code?: string } | undefined)
    ?.code;
const defaultCountry =
    COUNTRIES.find((c) => c.code === dealerCountryCode) ?? COUNTRIES[0];

const country = ref<Country>(defaultCountry);
const display = ref(''); // ekranda ko'rinadigan formatli matn
const pickerOpen = ref(false);

function format(digits: string, c: Country): string {
    const d = digits.slice(0, c.digits);
    let out = '';

    for (let i = 0; i < d.length; i++) {
        if (c.groups.includes(i)) {
            out += ' ';
        }

        out += d[i];
    }

    return out;
}

function rawDigits(): string {
    return display.value.replace(/\D/g, '').slice(0, country.value.digits);
}

function emitValue() {
    const d = rawDigits();
    emit('update:modelValue', d ? `${country.value.prefix}${d}` : '');
}

function onInput(e: Event) {
    const el = e.target as HTMLInputElement;
    const digits = el.value.replace(/\D/g, '').slice(0, country.value.digits);
    display.value = format(digits, country.value);
    // DOM'ni majburan moslaymiz — harf yoki mask'dan ortiq raqam darhol o'chadi
    // (`:value` bir tomonlama bo'lgani uchun, qiymat o'zgarmasa Vue DOM'ni yangilamaydi).
    el.value = display.value;
    emitValue();
}

// Harf/belgi bosilishini boshidan to'samiz (raqam, navigatsiya, boshqaruv tugmalari ruxsat).
function onKeydown(e: KeyboardEvent) {
    if (e.ctrlKey || e.metaKey || e.altKey || e.key.length !== 1) {
        return;
    }

    if (!/[0-9]/.test(e.key)) {
        e.preventDefault();
    }
}

function onPaste(e: ClipboardEvent) {
    e.preventDefault();
    const text = e.clipboardData?.getData('text') ?? '';
    const digits = text.replace(/\D/g, '').slice(0, country.value.digits);
    display.value = format(digits, country.value);
    emitValue();
}

function selectCountry(c: Country) {
    pickerOpen.value = false;

    if (c.code !== country.value.code) {
        const digits = rawDigits();
        country.value = c;
        display.value = format(digits, c);
        emitValue();
    }
}

// Tashqi modelValue o'zgarsa (forma reset/yuklash) — ichki holatni moslaymiz.
// O'zimiz emit qilgan qiymat bo'lsa, qayta formatlamaymiz (lag/kursor sakrashi yo'q).
watch(
    () => props.modelValue,
    (v) => {
        const current = rawDigits()
            ? `${country.value.prefix}${rawDigits()}`
            : '';

        if ((v ?? '') === current) {
            return;
        }

        const value = (v ?? '').trim();
        const match = [...COUNTRIES]
            .sort((a, b) => b.prefix.length - a.prefix.length)
            .find((c) => value.startsWith(c.prefix));

        if (match) {
            country.value = match;
            display.value = format(
                value.slice(match.prefix.length).replace(/\D/g, ''),
                match,
            );
        } else {
            display.value = format(value.replace(/\D/g, ''), country.value);
        }
    },
    { immediate: true },
);
</script>

<template>
    <div
        class="flex h-10 items-center rounded-md border bg-background"
        :class="error ? 'border-destructive' : 'border-input'"
    >
        <div class="relative h-full">
            <button
                type="button"
                :disabled="disabled"
                class="flex h-full items-center gap-1.5 rounded-l-md px-3 text-sm font-medium hover:bg-muted disabled:opacity-50"
                @click="pickerOpen = !pickerOpen"
            >
                <span class="text-base leading-none">{{ country.flag }}</span>
                <span>{{ country.prefix }}</span>
                <svg
                    class="h-4 w-4 text-muted-foreground"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                >
                    <path d="m6 9 6 6 6-6" />
                </svg>
            </button>
            <div
                v-if="pickerOpen"
                class="fixed inset-0 z-20"
                @click="pickerOpen = false"
            />
            <div
                v-if="pickerOpen"
                class="absolute top-full left-0 z-30 mt-1 min-w-[190px] overflow-hidden rounded-md border bg-popover py-1 shadow-md"
            >
                <button
                    v-for="c in COUNTRIES"
                    :key="c.code"
                    type="button"
                    class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm hover:bg-muted"
                    :class="
                        c.code === country.code ? 'bg-muted/50 font-medium' : ''
                    "
                    @click="selectCountry(c)"
                >
                    <span class="text-base">{{ c.flag }}</span>
                    <span class="flex-1">{{ c.name }}</span>
                    <span class="text-muted-foreground">{{ c.prefix }}</span>
                </button>
            </div>
        </div>
        <div class="h-6 w-px bg-border" />
        <input
            :value="display"
            :disabled="disabled"
            type="tel"
            inputmode="numeric"
            autocomplete="tel"
            :placeholder="country.sample"
            maxlength="20"
            class="h-full flex-1 bg-transparent px-3 text-sm outline-none placeholder:text-muted-foreground disabled:opacity-50"
            @input="onInput"
            @keydown="onKeydown"
            @paste="onPaste"
        />
    </div>
</template>
