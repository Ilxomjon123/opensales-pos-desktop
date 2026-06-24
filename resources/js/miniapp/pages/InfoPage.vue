<script setup lang="ts">
import {
    AtSign,
    Bell,
    Bot,
    ChevronRight,
    ClipboardList,
    Globe,
    Info,
    Phone,
    ShoppingBag,
    ShoppingCart,
    Sparkles,
    Wallet,
} from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { getTg, useApi } from '../composables/useApi';
import { readCache, writeCache } from '../composables/usePersistentCache';

type DealerInfo = {
    id: number;
    name: string;
    bot_username: string | null;
    bot_display_name: string;
    bot_short_description: string;
    bot_description: string;
    contact_phone: string | null;
};

type ProjectInfo = {
    name: string;
    url: string;
    support_telegram: string | null;
    version: string;
};

const { t } = useI18n();
const api = useApi();

const INFO_TTL = 24 * 60 * 60 * 1000; // 1 kun
const CACHE_KEY = 'miniapp:info';

const dealer = ref<DealerInfo | null>(null);
const project = ref<ProjectInfo | null>(null);
const loading = ref(true);
const errorMsg = ref('');

function apply(data: { dealer: DealerInfo; project: ProjectInfo }): void {
    dealer.value = data.dealer;
    project.value = data.project;
}

onMounted(async () => {
    const cached = readCache<{ dealer: DealerInfo; project: ProjectInfo }>(CACHE_KEY, INFO_TTL);

    if (cached) {
        apply(cached);
        loading.value = false;
    }

    try {
        const res: { dealer: DealerInfo; project: ProjectInfo } = await api.get('/info');
        writeCache(CACHE_KEY, res);
        apply(res);
    } catch (e: any) {
        if (!cached) {
            errorMsg.value = e?.message ?? t('miniappPages.app.genericError');
        }
    } finally {
        loading.value = false;
    }
});

function openLink(url: string): void {
    const tg = getTg();
    const isTelegramLink = /^https?:\/\/(t\.me|telegram\.me)\//i.test(url);

    if (isTelegramLink && tg?.openTelegramLink) {
        tg.openTelegramLink(url);

        return;
    }

    if (tg?.openLink) {
        tg.openLink(url);

        return;
    }

    window.open(url, '_blank', 'noopener,noreferrer');
}

function telHref(phone: string): string {
    return `tel:${phone.replace(/\s+/g, '')}`;
}

const projectInitial = computed<string>(() => {
    const name = project.value?.name ?? '?';

    return (name.trim()[0] ?? '?').toUpperCase();
});

const supportUsername = computed<string>(() => {
    const raw = (project.value?.support_telegram ?? '').trim();
    if (!raw) {
        return '';
    }

    return raw
        .replace(/^https?:\/\/(?:t|telegram)\.me\//i, '')
        .replace(/^@+/, '')
        .replace(/\/+$/, '');
});

const botInitial = computed<string>(() => {
    const name = dealer.value?.bot_display_name ?? dealer.value?.name ?? '?';

    return (name.trim()[0] ?? '?').toUpperCase();
});

type FeatureKey = 'catalog' | 'cart' | 'orders' | 'finance' | 'multibot' | 'realtime';

const features = computed<Array<{ key: FeatureKey; icon: typeof ShoppingBag; tint: string }>>(() => [
    { key: 'catalog', icon: ShoppingBag, tint: 'bg-emerald-500' },
    { key: 'cart', icon: ShoppingCart, tint: 'bg-sky-500' },
    { key: 'orders', icon: ClipboardList, tint: 'bg-amber-500' },
    { key: 'finance', icon: Wallet, tint: 'bg-violet-500' },
    { key: 'multibot', icon: Bot, tint: 'bg-rose-500' },
    { key: 'realtime', icon: Bell, tint: 'bg-cyan-500' },
]);

const hasDealerSection = computed<boolean>(() => Boolean(dealer.value?.bot_username || dealer.value?.contact_phone));
</script>

<template>
    <div class="mx-auto w-full max-w-2xl px-4 pb-6 pt-4">
        <!-- Skeleton -->
        <div v-if="loading && !project" class="space-y-5">
            <div class="h-52 animate-pulse rounded-3xl bg-muted" />
            <div class="space-y-2">
                <div class="ml-4 h-3 w-28 animate-pulse rounded bg-muted" />
                <div class="grid grid-cols-2 gap-3">
                    <div v-for="i in 4" :key="i" class="h-24 animate-pulse rounded-2xl bg-muted" />
                </div>
            </div>
            <div class="space-y-2">
                <div class="ml-4 h-3 w-24 animate-pulse rounded bg-muted" />
                <div class="h-28 animate-pulse rounded-2xl bg-muted" />
            </div>
        </div>

        <!-- Xatolik -->
        <div
            v-else-if="errorMsg && !project"
            class="flex flex-col items-center gap-3 rounded-2xl border border-amber-500/30 bg-amber-500/5 p-6 text-center"
        >
            <Info class="h-7 w-7 text-amber-600 dark:text-amber-400" />
            <p class="text-sm text-muted-foreground">{{ errorMsg }}</p>
        </div>

        <!-- Asosiy kontent -->
        <div v-else-if="project" class="space-y-5">
            <!-- Project hero -->
            <section
                class="relative overflow-hidden rounded-3xl border border-border bg-gradient-to-b from-primary/10 via-card to-card px-5 pb-6 pt-7 text-center shadow-sm"
            >
                <div
                    class="mx-auto flex h-20 w-20 items-center justify-center rounded-2xl bg-gradient-to-br from-primary to-primary/70 text-2xl font-bold text-primary-foreground shadow-lg ring-4 ring-background"
                >
                    {{ projectInitial }}
                </div>
                <h1 class="mt-4 break-words text-xl font-bold leading-tight">{{ project.name }}</h1>
                <p class="mt-1 text-sm font-medium text-primary">{{ t('miniappPages.info.projectTagline') }}</p>
                <p class="mt-3 break-words text-[13px] leading-relaxed text-muted-foreground">
                    {{ t('miniappPages.info.projectDescription') }}
                </p>
            </section>

            <!-- Features -->
            <section>
                <h2 class="mb-2 px-4 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                    {{ t('miniappPages.info.featuresTitle') }}
                </h2>
                <div class="grid grid-cols-2 gap-3">
                    <div
                        v-for="f in features"
                        :key="f.key"
                        class="flex flex-col gap-2 rounded-2xl border border-border bg-card p-3.5"
                    >
                        <div :class="['flex h-9 w-9 items-center justify-center rounded-xl text-white', f.tint]">
                            <component :is="f.icon" class="h-4.5 w-4.5" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-[14px] font-semibold leading-tight">
                                {{ t(`miniappPages.info.features.${f.key}Title`) }}
                            </p>
                            <p class="mt-1 break-words text-[12px] leading-snug text-muted-foreground">
                                {{ t(`miniappPages.info.features.${f.key}Text`) }}
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Aloqa va sayt -->
            <section>
                <h2 class="mb-2 px-4 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                    {{ t('miniappPages.info.contactTitle') }}
                </h2>
                <div class="divide-y divide-border overflow-hidden rounded-2xl border border-border bg-card">
                    <button
                        type="button"
                        class="flex w-full items-center gap-3 px-4 py-3.5 text-left transition-colors active:bg-muted/60"
                        @click="openLink(project.url)"
                    >
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-cyan-500 text-white">
                            <Globe class="h-4.5 w-4.5" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-[15px] font-semibold leading-tight">{{ t('miniappPages.info.projectWebsite') }}</p>
                            <p class="mt-0.5 truncate text-[13px] text-cyan-600 dark:text-cyan-400">{{ project.url }}</p>
                        </div>
                        <ChevronRight class="h-5 w-5 shrink-0 text-muted-foreground/60" />
                    </button>

                    <button
                        v-if="supportUsername"
                        type="button"
                        class="flex w-full items-center gap-3 px-4 py-3.5 text-left transition-colors active:bg-muted/60"
                        @click="openLink(`https://t.me/${supportUsername}`)"
                    >
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-blue-500 text-white">
                            <AtSign class="h-4.5 w-4.5" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-[15px] font-semibold leading-tight">{{ t('miniappPages.info.supportContact') }}</p>
                            <p class="mt-0.5 truncate font-mono text-[13px] text-blue-600 dark:text-blue-400">@{{ supportUsername }}</p>
                        </div>
                        <ChevronRight class="h-5 w-5 shrink-0 text-muted-foreground/60" />
                    </button>
                </div>
            </section>

            <!-- Dealer (kompakt bo'lim) -->
            <section v-if="dealer && hasDealerSection">
                <h2 class="mb-2 px-4 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                    {{ t('miniappPages.info.dealerTitle') }}
                </h2>
                <div class="divide-y divide-border overflow-hidden rounded-2xl border border-border bg-card">
                    <button
                        v-if="dealer.bot_username"
                        type="button"
                        class="flex w-full items-center gap-3 px-4 py-3.5 text-left transition-colors active:bg-muted/60"
                        @click="openLink(`https://t.me/${dealer.bot_username}`)"
                    >
                        <div
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-primary to-primary/70 text-sm font-bold text-primary-foreground"
                        >
                            {{ botInitial }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-[15px] font-semibold leading-tight">{{ dealer.bot_display_name }}</p>
                            <p class="mt-0.5 truncate font-mono text-[13px] text-sky-600 dark:text-sky-400">@{{ dealer.bot_username }}</p>
                        </div>
                        <ChevronRight class="h-5 w-5 shrink-0 text-muted-foreground/60" />
                    </button>

                    <a
                        v-if="dealer.contact_phone"
                        :href="telHref(dealer.contact_phone)"
                        class="flex w-full items-center gap-3 px-4 py-3.5 text-left transition-colors active:bg-muted/60"
                    >
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-rose-500 text-white">
                            <Phone class="h-4.5 w-4.5" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-[15px] font-semibold leading-tight">{{ t('miniappPages.info.contactPhone') }}</p>
                            <p class="mt-0.5 truncate font-mono text-[13px] text-rose-600 dark:text-rose-400">{{ dealer.contact_phone }}</p>
                        </div>
                        <ChevronRight class="h-5 w-5 shrink-0 text-muted-foreground/60" />
                    </a>
                </div>
            </section>

            <!-- Footer: version + poweredBy -->
            <div class="flex flex-col items-center gap-1 px-2 pt-1 text-center">
                <div class="inline-flex items-center gap-1.5 rounded-full bg-muted/60 px-3 py-1 text-[11px] font-medium text-muted-foreground">
                    <Sparkles class="h-3 w-3" />
                    {{ project.name }} · {{ t('miniappPages.info.versionLabel', { version: project.version }) }}
                </div>
                <p class="text-[11px] text-muted-foreground/70">
                    {{ t('miniappPages.info.poweredBy', { name: project.name }) }}
                </p>
            </div>
        </div>
    </div>
</template>
