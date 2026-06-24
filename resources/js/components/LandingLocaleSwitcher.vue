<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Check, Globe } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useLocale } from '@/composables/useLocale';
import type { SupportedLocale } from '@/i18n';

const { current, locales } = useLocale();
const i18n = useI18n();

const open = ref(false);
const root = ref<HTMLElement | null>(null);

const active = computed(() => locales.value.find((l) => l.code === current.value) ?? locales.value[0]);

// Faqat uz-dan boshqa tillarda URL prefiksi bo'ladi.
const PREFIX = /^\/(ru|en|uz-Cyrl)(?=\/|$)/;

function switchTo(code: SupportedLocale): void {
    open.value = false;

    if (code === current.value) {
        return;
    }

    const path = window.location.pathname.replace(PREFIX, '') || '/';
    const target = code === 'uz' ? path : `/${code}${path === '/' ? '' : path}`;

    i18n.locale.value = code;
    router.visit(target + window.location.hash);
}

function toggle(): void {
    open.value = !open.value;
}

function onClickOutside(event: MouseEvent): void {
    if (root.value && !root.value.contains(event.target as Node)) {
        open.value = false;
    }
}

onMounted(() => document.addEventListener('click', onClickOutside));
onBeforeUnmount(() => document.removeEventListener('click', onClickOutside));
</script>

<template>
    <div ref="root" class="relative">
        <button
            type="button"
            class="inline-flex size-9 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
            :aria-expanded="open"
            aria-haspopup="menu"
            :aria-label="active?.native"
            :title="active?.native"
            @click="toggle"
        >
            <Globe class="size-4" />
        </button>

        <div
            v-if="open"
            class="absolute right-0 z-50 mt-1 min-w-44 overflow-hidden rounded-md border border-border/60 bg-background py-1 shadow-lg"
            role="menu"
        >
            <button
                v-for="entry in locales"
                :key="entry.code"
                type="button"
                class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-foreground hover:bg-accent"
                role="menuitem"
                @click="switchTo(entry.code)"
            >
                <span aria-hidden="true">{{ entry.flag }}</span>
                <span class="flex-1">{{ entry.native }}</span>
                <Check v-if="entry.code === current" class="size-4 text-primary" />
            </button>
        </div>
    </div>
</template>
