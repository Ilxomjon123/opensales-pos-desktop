<script setup lang="ts">
import { AlertTriangle, ChevronRight, Info, LayoutGrid, Receipt, Repeat2, ShoppingCart, Store } from 'lucide-vue-next';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import LocaleSwitcherMini from './components/LocaleSwitcherMini.vue';
import { useMiniApp, useApi, setActiveShopId, lastShopStorageKey } from './composables/useApi';
import { useCartStore } from './composables/useCartStore';
import { readCache, writeCache } from './composables/usePersistentCache';
import CartPage from './pages/CartPage.vue';
import CatalogPage from './pages/CatalogPage.vue';
import InfoPage from './pages/InfoPage.vue';
import OrdersPage from './pages/OrdersPage.vue';

const { t } = useI18n();

type Tab = 'catalog' | 'cart' | 'orders' | 'info';

const VALID_TABS: readonly Tab[] = ['catalog', 'cart', 'orders', 'info'] as const;
const SHOPS_TTL = 24 * 60 * 60 * 1000; // 1 kun

type ShopInfo = {
    id: number;
    name: string;
    phone?: string | null;
    address?: string | null;
    balance: number;
    photo_url?: string | null;
};

const { init, dealerId } = useMiniApp();
const api = useApi();
const cartStore = useCartStore();

function tabStorageKey(): string {
    return `miniapp:tab:${dealerId}`;
}

function loadInitialTab(): Tab {
    try {
        const saved = localStorage.getItem(tabStorageKey());

        if (saved && (VALID_TABS as readonly string[]).includes(saved)) {
            return saved as Tab;
        }
    } catch { /* */ }

    return 'catalog';
}

// Deep-link: botdagi "Buyurtmani ochish" tugmasi ?order=<id>&msg=<id> bilan keladi.
const deepLinkOrderId = ref<number | null>(null);
const deepLinkMessageId = ref<number | null>(null);
const deepLinkProductId = ref<number | null>(null);

function readQueryId(key: string): number | null {
    try {
        const id = Number(new URLSearchParams(window.location.search).get(key) ?? '');

        return Number.isFinite(id) && id > 0 ? id : null;
    } catch {
        return null;
    }
}

const initialDeepLink = readQueryId('order');
const initialDeepLinkMsg = readQueryId('msg');
// Narx o'zgarishi xabaridagi tugma ?product=<id> bilan keladi — katalogda ochamiz.
const initialDeepLinkProduct = readQueryId('product');

const tab = ref<Tab>(initialDeepLink ? 'orders' : initialDeepLinkProduct ? 'catalog' : loadInitialTab());
const loading = ref(true);
const errorMsg = ref('');

const shops = ref<ShopInfo[]>([]);
const activeShop = ref<ShopInfo | null>(null);
const showPicker = ref(false);
const cartCount = computed(() => cartStore.state.count);

const hasMultipleShops = computed(() => shops.value.length > 1);

const currentPageComponent = computed(() => {
    switch (tab.value) {
        case 'catalog': return CatalogPage;
        case 'cart': return CartPage;
        case 'orders': return OrdersPage;
        case 'info': return InfoPage;
    }
});

watch(tab, (t) => {
    try {
 localStorage.setItem(tabStorageKey(), t); 
} catch { /* */ }
});

function shopsCacheKey(): string {
    return `shops:${dealerId}`;
}

function applyShops(list: ShopInfo[]): boolean {
    shops.value = list;

    if (list.length === 0) {
        errorMsg.value = t('miniappPages.app.noShops');

        return false;
    }

    // Deep-link shop_id (botdan) — boshqa saqlangan mijozdan ustun
    const urlShopId = Number(new URLSearchParams(window.location.search).get('shop_id') ?? '') || null;
    const urlShop = urlShopId ? list.find((s) => s.id === urlShopId) : null;

    const savedId = Number(localStorage.getItem(lastShopStorageKey(dealerId)) ?? '') || null;
    const savedShop = savedId ? list.find((s) => s.id === savedId) : null;

    if (urlShop) {
        selectShop(urlShop);
    } else if (savedShop) {
        selectShop(savedShop);
    } else if (list.length === 1) {
        selectShop(list[0]);
    } else if (!activeShop.value) {
        showPicker.value = true;
    }

    return true;
}

onMounted(async () => {
    init();

    // Deep-link buyurtma + xabar — OrdersPage prop orqali oladi
    deepLinkOrderId.value = initialDeepLink;
    deepLinkMessageId.value = initialDeepLinkMsg;
    deepLinkProductId.value = initialDeepLinkProduct;

    // 1) Keshdan darhol ko'rsatamiz — UI bloklanmaydi
    const cached = readCache<ShopInfo[]>(shopsCacheKey(), SHOPS_TTL);

    if (cached && cached.length > 0) {
        applyShops(cached);
        loading.value = false;
    }

    // 2) Yangi ma'lumotni foncha tortib olamiz
    try {
        const res: { shops: ShopInfo[] } = await api.get('/shops');
        const fresh = (res.shops ?? []).map((s: any) => (s.data ?? s) as ShopInfo);
        writeCache(shopsCacheKey(), fresh);
        applyShops(fresh);
    } catch (e: any) {
        if (!cached) {
            console.error('MiniApp init error:', e);
            errorMsg.value = e.message ?? t('miniappPages.app.genericError');
        }
    }

    loading.value = false;
});

function selectShop(shop: ShopInfo): void {
    activeShop.value = shop;
    setActiveShopId(shop.id);
    localStorage.setItem(lastShopStorageKey(dealerId), String(shop.id));
    showPicker.value = false;

    // Savatni fonga olamiz — UI ni bloklamasin
    void cartStore.reset(shop.id);
}

function openSwitcher() {
    showPicker.value = true;
}

function onOrderPlaced() {
    cartStore.clearLocal();
    tab.value = 'orders';
}

function onGoToCart() {
    tab.value = 'cart';
}

function onGoToCatalog() {
    tab.value = 'catalog';
}

function shopInitial(name: string): string {
    return (name?.trim()?.[0] ?? '?').toUpperCase();
}

function formatMoney(n: number): string {
    return String(Math.round(n)).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
}

// Tab bar — "water droplet" (liquid glass) indikatori pozitsiyasi
const tabButtons: Record<Tab, HTMLButtonElement | null> = {
    catalog: null,
    cart: null,
    orders: null,
    info: null,
};

function setTabRef(key: Tab) {
    return (el: any) => {
        tabButtons[key] = el as HTMLButtonElement | null;
    };
}

const dropletStyle = ref<{ left: string; width: string }>({ left: '0px', width: '0px' });

function updateDroplet() {
    const btn = tabButtons[tab.value];
    const parent = btn?.parentElement;

    if (!btn || !parent) {
return;
}

    const parentRect = parent.getBoundingClientRect();
    const rect = btn.getBoundingClientRect();
    dropletStyle.value = {
        left: `${rect.left - parentRect.left}px`,
        width: `${rect.width}px`,
    };
}

watch(tab, () => nextTick(updateDroplet));
watch([activeShop, loading, showPicker], () => nextTick(updateDroplet));

onMounted(() => {
    nextTick(updateDroplet);
    window.addEventListener('resize', updateDroplet);
});

onUnmounted(() => {
    window.removeEventListener('resize', updateDroplet);
});
</script>

<template>
    <div class="miniapp-root flex h-screen flex-col overflow-x-hidden overflow-y-auto bg-background text-foreground">
        <!-- Header -->
        <header v-if="activeShop" class="relative z-40 border-b bg-background/90 backdrop-blur-md">
            <div class="mx-auto flex w-full max-w-7xl items-center gap-3 px-4 py-2.5">
                <!-- Avatar -->
                <div class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full bg-primary/10 text-primary">
                    <img v-if="activeShop.photo_url" :src="activeShop.photo_url" :alt="activeShop.name" class="h-full w-full object-cover" />
                    <span v-else class="text-sm font-semibold">{{ shopInitial(activeShop.name) }}</span>
                </div>

                <!-- Name + balance -->
                <div class="min-w-0 flex-1">
                    <h1 class="truncate text-sm font-semibold leading-tight">{{ activeShop.name }}</h1>
                    <p class="mt-0.5 flex items-center gap-1 text-xs leading-tight">
                        <span class="text-muted-foreground">{{ t('miniappPages.app.balanceLabel') }}</span>
                        <span
                            class="font-semibold"
                            :class="activeShop.balance < 0 ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400'"
                        >
                            {{ formatMoney(activeShop.balance) }}&nbsp;so'm
                        </span>
                    </p>
                </div>

                <LocaleSwitcherMini />

                <button
                    v-if="hasMultipleShops"
                    type="button"
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-border text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                    :aria-label="t('miniappPages.app.switchShopAria')"
                    @click="openSwitcher"
                >
                    <Repeat2 class="h-4 w-4" />
                </button>
            </div>
        </header>

        <!-- Xato -->
        <div v-if="errorMsg && !loading" class="flex flex-1 flex-col items-center justify-center gap-3 p-6 text-center">
            <div class="flex h-16 w-16 items-center justify-center rounded-full bg-amber-500/10 text-amber-600 dark:text-amber-400">
                <AlertTriangle class="h-7 w-7" />
            </div>
            <p class="max-w-sm text-sm text-muted-foreground">{{ errorMsg }}</p>
        </div>

        <!-- Yuklanmoqda — skeleton -->
        <div v-else-if="loading" class="mx-auto w-full max-w-7xl flex-1 p-3 sm:p-4">
            <div class="mb-4 h-11 w-full animate-pulse rounded-lg bg-muted" />
            <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-3 sm:gap-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
                <div v-for="n in 12" :key="n" class="overflow-hidden rounded-xl border bg-card">
                    <div class="aspect-square animate-pulse bg-muted" />
                    <div class="space-y-2 p-2">
                        <div class="h-3 w-3/4 animate-pulse rounded bg-muted" />
                        <div class="h-2.5 w-1/2 animate-pulse rounded bg-muted" />
                        <div class="mt-3 flex items-end justify-between gap-1">
                            <div class="h-3 w-1/3 animate-pulse rounded bg-muted" />
                            <div class="h-3 w-6 animate-pulse rounded bg-muted" />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mijoz tanlash -->
        <div v-else-if="showPicker" class="mx-auto flex w-full max-w-3xl flex-1 flex-col p-4 sm:p-6">
            <div class="mb-6 flex flex-col items-center text-center">
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                    <Store class="h-7 w-7" />
                </div>
                <h2 class="mt-4 text-xl font-bold tracking-tight">{{ t('miniappPages.app.pickerTitle') }}</h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ t('miniappPages.app.pickerSubtitle') }}
                </p>
            </div>

            <div class="grid gap-2.5 sm:grid-cols-2">
                <button
                    v-for="s in shops"
                    :key="s.id"
                    type="button"
                    class="group flex w-full min-w-0 items-center gap-3 overflow-hidden rounded-2xl border bg-card p-3.5 text-left transition-all hover:border-primary/60 hover:shadow-sm active:scale-[0.99]"
                    :class="activeShop?.id === s.id ? 'border-primary bg-primary/5' : 'border-border'"
                    @click="selectShop(s)"
                >
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-primary/10 text-primary">
                        <img v-if="s.photo_url" :src="s.photo_url" :alt="s.name" class="h-full w-full object-cover" />
                        <span v-else class="text-lg font-semibold">{{ shopInitial(s.name) }}</span>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="line-clamp-2 break-words text-sm font-semibold leading-snug">{{ s.name }}</p>
                        <p v-if="s.address" class="mt-0.5 truncate text-xs text-muted-foreground">{{ s.address }}</p>
                        <p class="mt-1 text-xs">
                            <span class="text-muted-foreground">{{ t('miniappPages.app.shopBalance') }} </span>
                            <span
                                class="font-semibold"
                                :class="s.balance < 0 ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400'"
                            >
                                {{ formatMoney(s.balance) }}&nbsp;so'm
                            </span>
                        </p>
                    </div>
                    <ChevronRight class="h-4 w-4 shrink-0 text-muted-foreground transition-transform group-hover:translate-x-0.5" />
                </button>
            </div>
        </div>

        <!-- Asosiy -->
        <main v-else-if="activeShop" class="flex-1 pb-[calc(88px+env(safe-area-inset-bottom))]">
            <KeepAlive :key="activeShop.id">
                <component
                    :is="currentPageComponent"
                    :deep-link-order-id="tab === 'orders' ? deepLinkOrderId : null"
                    :deep-link-message-id="tab === 'orders' ? deepLinkMessageId : null"
                    :deep-link-product-id="tab === 'catalog' ? deepLinkProductId : null"
                    @go-to-cart="onGoToCart"
                    @go-to-catalog="onGoToCatalog"
                    @order-placed="onOrderPlaced"
                />
            </KeepAlive>
        </main>

        <!-- iOS liquid-glass floating tab-bar -->
        <nav
            v-if="activeShop && !showPicker && !loading"
            class="pointer-events-none fixed inset-x-0 bottom-0 z-20 flex justify-center pb-[calc(env(safe-area-inset-bottom)+10px)]"
        >
            <div
                class="pointer-events-auto relative flex items-center rounded-full border border-white/40 bg-white/55 p-1.5 shadow-[0_8px_32px_-6px_rgba(15,15,35,0.25),inset_0_1px_0_rgba(255,255,255,0.7)] backdrop-blur-2xl backdrop-saturate-150 dark:border-white/10 dark:bg-white/10 dark:shadow-[0_8px_32px_-6px_rgba(0,0,0,0.5),inset_0_1px_0_rgba(255,255,255,0.12)]"
            >
                <!-- Suv tomchisi (liquid glass droplet) -->
                <span
                    class="pointer-events-none absolute top-1.5 bottom-1.5 rounded-full border border-white/60 bg-gradient-to-b from-white/95 to-white/70 shadow-[0_4px_14px_-2px_rgba(15,15,35,0.2),inset_0_1px_0_rgba(255,255,255,0.9),inset_0_-2px_6px_-2px_rgba(15,15,35,0.08)] dark:border-white/20 dark:from-white/25 dark:to-white/10 dark:shadow-[0_4px_14px_-2px_rgba(0,0,0,0.35),inset_0_1px_0_rgba(255,255,255,0.3)]"
                    :style="{
                        left: dropletStyle.left,
                        width: dropletStyle.width,
                        transitionProperty: 'left, width, transform',
                        transitionDuration: '520ms',
                        transitionTimingFunction: 'cubic-bezier(0.68, -0.2, 0.27, 1.35)',
                    }"
                />

                <button
                    v-for="tabItem in [
                        { key: 'catalog' as Tab, label: t('miniappPages.app.tabCatalog'), icon: LayoutGrid },
                        { key: 'cart' as Tab, label: t('miniappPages.app.tabCart'), icon: ShoppingCart },
                        { key: 'orders' as Tab, label: t('miniappPages.app.tabOrders'), icon: Receipt },
                        { key: 'info' as Tab, label: t('miniappPages.app.tabInfo'), icon: Info },
                    ]"
                    :key="tabItem.key"
                    :ref="setTabRef(tabItem.key)"
                    type="button"
                    class="relative z-10 flex items-center gap-1.5 rounded-full px-3 py-2 text-[12.5px] font-semibold transition-colors duration-300 active:scale-95 sm:gap-2 sm:px-3.5 sm:text-[13.5px]"
                    :class="tab === tabItem.key ? 'text-foreground' : 'text-foreground/70 hover:text-foreground'"
                    @click="tab = tabItem.key"
                >
                    <component :is="tabItem.icon" class="h-[17px] w-[17px] shrink-0 sm:h-[18px] sm:w-[18px]" :stroke-width="tab === tabItem.key ? 2.5 : 2" />
                    <span class="whitespace-nowrap">{{ tabItem.label }}</span>
                    <span
                        v-if="tabItem.key === 'cart' && cartCount > 0"
                        class="absolute -right-1 -top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-bold text-white shadow-sm ring-2 ring-white dark:ring-zinc-900"
                    >
                        {{ cartCount }}
                    </span>
                </button>
            </div>
        </nav>
    </div>
</template>

<style>
.miniapp-root {
    --muted-foreground: var(--foreground);
    --color-muted-foreground: var(--foreground);
}
</style>
