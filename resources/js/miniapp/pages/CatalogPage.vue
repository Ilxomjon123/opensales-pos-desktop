<script setup lang="ts">
import { Image as ImageIcon, LayoutGrid, Package, PackageSearch, Search } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import ProductDetailModal from '../components/ProductDetailModal.vue';
import type {CartItemState, ProductInfo} from '../components/ProductDetailModal.vue';
import { getActiveShopId, useApi } from '../composables/useApi';
import { useCartStore } from '../composables/useCartStore';
import { readCache, writeCache } from '../composables/usePersistentCache';

defineEmits<{ goToCart: [] }>();
const props = defineProps<{ deepLinkProductId?: number | null }>();
const api = useApi();
const cartStore = useCartStore();
const { t } = useI18n();

type Category = { id: number; name: string };
type ViewMode = 'gallery' | 'detailed';

const PRODUCTS_TTL = 30 * 60 * 1000; // 30 daqiqa
const CATEGORIES_TTL = 24 * 60 * 60 * 1000; // 1 kun
const VIEW_MODE_AUTO_THRESHOLD = 20;

// Eski versiyalarda saqlangan kalitlarni tozalaymiz — endi viewMode mahsulot
// soniga qarab har safar yuklanganda avtomatik tanlanadi, qo'lda tanlov esa
// faqat joriy sessiya davomida saqlanadi.
try {
    localStorage.removeItem('miniapp:catalogViewMode');
    localStorage.removeItem('miniapp:catalogViewModeManual');
} catch { /* */ }

const products = ref<ProductInfo[]>([]);
const categories = ref<Category[]>([]);
const activeCategoryId = ref<number | null>(null);
const search = ref('');
const loading = ref(true);
const loadingMore = ref(false);
const currentPage = ref(1);
const lastPage = ref(1);

const viewMode = ref<ViewMode>('gallery');

function setViewMode(m: ViewMode) {
    viewMode.value = m;
}

function applyAutoViewMode(total: number) {
    viewMode.value = total < VIEW_MODE_AUTO_THRESHOLD ? 'detailed' : 'gallery';
}

function productsCacheKey(): string | null {
    const shopId = getActiveShopId();

    if (!shopId) {
return null;
}

    // Faqat default holat keshlanadi (filtrsiz, qidiruvsiz, 1-sahifa)
    if (search.value || activeCategoryId.value !== null) {
return null;
}

    return `products:${shopId}:p1`;
}

function categoriesCacheKey(): string | null {
    const shopId = getActiveShopId();

    if (!shopId) {
return null;
}

    return `categories:${shopId}`;
}

const selectedProduct = ref<ProductInfo | null>(null);
const modalOpen = ref(false);

const selectedCartItem = computed<CartItemState | null>(() => {
    const p = selectedProduct.value;

    if (!p) {
return null;
}

    const found = cartStore.findItem(p.id);

    if (!found) {
return null;
}

    return { qty: found.qty, pack_qty: found.pack_qty ?? null };
});

const sentinel = ref<HTMLElement | null>(null);
let observer: IntersectionObserver | null = null;
let searchTimer: ReturnType<typeof setTimeout> | null = null;

function buildUrl(page: number): string {
    const params = new URLSearchParams();
    params.set('page', String(page));
    const q = search.value.trim();

    if (q) {
params.set('search', q);
}

    if (activeCategoryId.value !== null) {
params.set('category_id', String(activeCategoryId.value));
}

    return `/products?${params.toString()}`;
}

async function loadCategories() {
    const cacheKey = categoriesCacheKey();

    if (cacheKey && categories.value.length === 0) {
        const cached = readCache<Category[]>(cacheKey, CATEGORIES_TTL);

        if (cached) {
categories.value = cached;
}
    }

    try {
        const res: { categories: Category[] } = await api.get('/categories');
        categories.value = res.categories;

        if (cacheKey) {
writeCache(cacheKey, res.categories);
}
    } catch { /* */ }
}

function selectCategory(id: number | null) {
    if (activeCategoryId.value === id) {
return;
}

    activeCategoryId.value = id;
    load();
}

async function load() {
    const cacheKey = productsCacheKey();

    // 1) Keshdan darhol ko'rsatamiz — loader yo'q
    if (cacheKey) {
        const cached = readCache<{ products: ProductInfo[]; lastPage: number; total?: number }>(cacheKey, PRODUCTS_TTL);

        if (cached) {
            products.value = cached.products;
            lastPage.value = cached.lastPage;
            currentPage.value = 1;
            loading.value = false;

            applyAutoViewMode(cached.total ?? cached.products.length);
        }
    }

    // Birinchi marta yuklashda (kesh yo'q va products bo'sh) — skeleton ko'rsatamiz.
    // Aks holda eski natijalarni saqlab, foncha yangilaymiz — skeleton flash yo'q.
    if (products.value.length === 0) {
        loading.value = true;
    }

    currentPage.value = 1;

    // 2) Foncha yangi ma'lumot tortib olamiz
    try {
        const res: any = await api.get(buildUrl(1));
        products.value = res.data;
        lastPage.value = res.meta?.last_page ?? 1;
        const total: number = res.meta?.total ?? res.data?.length ?? 0;
        applyAutoViewMode(total);

        if (cacheKey) {
            writeCache(cacheKey, { products: res.data, lastPage: lastPage.value, total });
        }
    } catch { /* */ }

    loading.value = false;
}

async function loadMore() {
    if (loadingMore.value || loading.value) {
return;
}

    if (currentPage.value >= lastPage.value) {
return;
}

    loadingMore.value = true;
    const next = currentPage.value + 1;

    try {
        const res: any = await api.get(buildUrl(next));
        products.value = [...products.value, ...(res.data ?? [])];
        currentPage.value = next;
        lastPage.value = res.meta?.last_page ?? lastPage.value;
    } catch { /* */ }

    loadingMore.value = false;
}

onMounted(() => {
    load();
    loadCategories();

    if (props.deepLinkProductId) {
        openDeepLinkProduct(props.deepLinkProductId);
    }

    observer = new IntersectionObserver(
        (entries) => {
            if (entries[0]?.isIntersecting) {
loadMore();
}
        },
        { rootMargin: '400px 0px' },
    );

    // Sentinel DOM ga keyin keladi — watch orqali ulaymiz
});

watch(sentinel, (el, _old) => {
    if (!observer) {
return;
}

    if (_old) {
observer.unobserve(_old);
}

    if (el) {
observer.observe(el);
}
});

onBeforeUnmount(() => {
    observer?.disconnect();
    observer = null;
});

watch(search, () => {
    if (searchTimer) {
clearTimeout(searchTimer);
}

    searchTimer = setTimeout(load, 300);
});

function firstImageOf(p: ProductInfo): string | null {
    if (p.images && p.images.length > 0) {
        return p.images[0].url;
    }

    return p.image_url ?? null;
}

function imageCountOf(p: ProductInfo): number {
    if (p.images && p.images.length > 0) {
        return p.images.length;
    }

    return p.image_url ? 1 : 0;
}

function selectProduct(p: ProductInfo) {
    selectedProduct.value = p;
    modalOpen.value = true;
}

// Deep-link: narx o'zgarishi xabaridan ?product=<id> bilan kelganda
// o'sha mahsulotni darhol ochamiz (ro'yxatda bo'lmasa ham alohida tortib olamiz).
async function openDeepLinkProduct(id: number) {
    try {
        const res: any = await api.get(`/products/${id}`);
        const p = (res?.data ?? res) as ProductInfo;

        if (p && p.id) {
            selectProduct(p);
        }
    } catch { /* topilmadi — e'tibor bermaymiz */ }
}

function formatMoney(n: number): string {
    return String(Math.round(n)).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
}
</script>

<template>
    <div class="mx-auto w-full max-w-7xl p-3 sm:p-4">
        <!-- Sticky: Search (live) + ko'rinish toggle + kategoriyalar -->
        <div class="sticky top-0 z-20 -mx-3 -mt-3 mb-3 bg-background/80 px-3 pb-3 pt-3 shadow-[0_10px_22px_-16px_rgba(15,15,35,0.25)] backdrop-blur-xl backdrop-saturate-150 dark:shadow-[0_10px_22px_-14px_rgba(0,0,0,0.7)] sm:-mx-4 sm:-mt-4 sm:mb-4 sm:px-4 sm:pb-3.5 sm:pt-4">
            <div class="flex items-center gap-2">
                <div class="relative flex-1">
                    <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <input
                        v-model="search"
                        :placeholder="t('miniappPages.catalog.searchPlaceholder')"
                        class="w-full rounded-xl border bg-background py-2.5 pl-10 pr-3 text-sm outline-none transition-shadow focus:border-primary focus:ring-2 focus:ring-primary/20"
                    />
                </div>
                <div class="flex shrink-0 items-center rounded-xl border bg-background p-0.5">
                    <button
                        type="button"
                        class="flex h-9 w-9 items-center justify-center rounded-lg transition-colors"
                        :class="viewMode === 'gallery' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground'"
                        :aria-label="t('miniappPages.catalog.viewGalleryAria')"
                        @click="setViewMode('gallery')"
                    >
                        <ImageIcon class="h-4 w-4" />
                    </button>
                    <button
                        type="button"
                        class="flex h-9 w-9 items-center justify-center rounded-lg transition-colors"
                        :class="viewMode === 'detailed' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground'"
                        :aria-label="t('miniappPages.catalog.viewDetailedAria')"
                        @click="setViewMode('detailed')"
                    >
                        <LayoutGrid class="h-4 w-4" />
                    </button>
                </div>
            </div>

            <!-- Kategoriyalar chip bar -->
            <div v-if="categories.length > 0" class="-mx-3 mt-2.5 overflow-x-auto px-3 sm:-mx-4 sm:px-4">
                <div class="flex gap-2">
                    <button
                        type="button"
                        class="shrink-0 rounded-full border px-3 py-1.5 text-xs font-medium transition-colors"
                        :class="activeCategoryId === null
                            ? 'border-primary bg-primary text-primary-foreground'
                            : 'border-border bg-background text-foreground/80 hover:bg-muted'"
                        @click="selectCategory(null)"
                    >
                        {{ t('miniappPages.catalog.categoryAll') }}
                    </button>
                    <button
                        v-for="c in categories"
                        :key="c.id"
                        type="button"
                        class="shrink-0 rounded-full border px-3 py-1.5 text-xs font-medium transition-colors"
                        :class="activeCategoryId === c.id
                            ? 'border-primary bg-primary text-primary-foreground'
                            : 'border-border bg-background text-foreground/80 hover:bg-muted'"
                        @click="selectCategory(c.id)"
                    >
                        {{ c.name }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Loading skeleton -->
        <div
            v-if="loading"
            :class="viewMode === 'gallery'
                ? '-mx-3 grid grid-cols-3 gap-px sm:-mx-4 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6 xl:grid-cols-8'
                : 'grid grid-cols-2 gap-2.5 sm:grid-cols-3 sm:gap-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6'"
        >
            <div
                v-for="n in 12"
                :key="n"
                :class="viewMode === 'gallery'
                    ? 'overflow-hidden bg-card'
                    : 'overflow-hidden rounded-xl border bg-card'"
            >
                <div class="aspect-square animate-pulse bg-muted" />
                <div v-if="viewMode === 'detailed'" class="space-y-2 p-2">
                    <div class="h-3 w-3/4 animate-pulse rounded bg-muted" />
                    <div class="h-2.5 w-1/2 animate-pulse rounded bg-muted" />
                    <div class="mt-3 flex items-end justify-between gap-1">
                        <div class="h-3 w-1/3 animate-pulse rounded bg-muted" />
                        <div class="h-3 w-6 animate-pulse rounded bg-muted" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Products grid -->
        <div
            v-else-if="products.length"
            :class="viewMode === 'gallery'
                ? '-mx-3 grid grid-cols-3 gap-px sm:-mx-4 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6 xl:grid-cols-8'
                : 'grid grid-cols-2 gap-2.5 sm:grid-cols-3 sm:gap-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6'"
        >
            <button
                v-for="(p, idx) in products"
                :key="p.id"
                v-memo="[p.id, viewMode]"
                :class="viewMode === 'gallery'
                    ? 'group relative overflow-hidden bg-card text-left active:opacity-80'
                    : 'group relative overflow-hidden rounded-xl border bg-card text-left transition-all hover:border-primary/50 hover:shadow-md active:scale-[0.98]'"
                @click="selectProduct(p)"
            >
                <div class="aspect-square overflow-hidden bg-muted">
                    <img
                        v-if="firstImageOf(p)"
                        :src="firstImageOf(p)!"
                        :alt="p.name"
                        loading="lazy"
                        decoding="async"
                        :fetchpriority="idx < 12 ? 'high' : 'low'"
                        :class="viewMode === 'gallery'
                            ? 'h-full w-full object-cover'
                            : 'h-full w-full object-cover transition-transform group-hover:scale-105'"
                    />
                    <div v-else class="flex h-full w-full items-center justify-center text-muted-foreground/30">
                        <Package class="h-8 w-8" />
                    </div>
                    <div v-if="imageCountOf(p) > 1" class="absolute right-1 top-1 rounded bg-black/50 px-1.5 py-0.5 text-[10px] text-white">
                        +{{ imageCountOf(p) - 1 }}
                    </div>
                    <div
                        v-if="(p.discount_percent ?? 0) > 0"
                        class="absolute left-1 bottom-1 rounded-md bg-rose-500 px-1.5 py-0.5 text-[10px] font-bold text-white shadow"
                    >
                        −{{ p.discount_percent }}%
                    </div>
                </div>
                <div v-if="viewMode === 'detailed'" class="p-2">
                    <p class="text-sm font-bold leading-tight line-clamp-2">{{ p.name }}</p>
                    <div class="mt-1.5 flex items-end justify-between gap-1">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-bold" :class="p.original_price ? 'text-rose-600 dark:text-rose-400' : ''">
                                {{ formatMoney(p.price) }}
                            </p>
                            <p v-if="p.original_price" class="truncate text-[10px] text-muted-foreground line-through">
                                {{ formatMoney(p.original_price) }}
                            </p>
                            <p v-else class="truncate text-[10px] text-muted-foreground">{{ t('miniappPages.catalog.perUnit', { unit: p.unit }) }}</p>
                            <p v-if="p.pack_size > 1" class="truncate text-[10px] text-muted-foreground">
                                {{ t('miniappPages.catalog.blockPrice', { price: formatMoney(p.pack_price) }) }}
                            </p>
                        </div>
                    </div>
                </div>
            </button>
        </div>

        <!-- Empty -->
        <div v-else class="flex flex-col items-center justify-center py-20 text-center">
            <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-muted text-muted-foreground">
                <PackageSearch class="h-7 w-7" />
            </div>
            <p class="mt-4 text-base font-semibold">{{ t('miniappPages.catalog.emptyTitle') }}</p>
            <p class="mt-1 text-sm text-muted-foreground">
                <template v-if="search">{{ t('miniappPages.catalog.emptySearch', { query: search }) }}</template>
                <template v-else>{{ t('miniappPages.catalog.emptyCategory') }}</template>
            </p>
        </div>

        <!-- Infinite scroll sentinel + loader -->
        <div
            v-if="!loading && products.length && currentPage < lastPage"
            ref="sentinel"
            class="flex items-center justify-center py-6"
        >
            <div class="flex items-center gap-2 text-xs text-muted-foreground">
                <span class="inline-block h-3 w-3 animate-spin rounded-full border-2 border-primary/30 border-t-primary" />
                {{ t('miniappPages.catalog.loadingMore') }}
            </div>
        </div>

        <p v-else-if="!loading && products.length && currentPage >= lastPage" class="py-6 text-center text-xs text-muted-foreground">
            {{ t('miniappPages.catalog.allShown', { count: products.length }) }}
        </p>

        <ProductDetailModal
            :product="selectedProduct"
            :open="modalOpen"
            :cart-item="selectedCartItem"
            @close="modalOpen = false"
            @go-to-cart="$emit('goToCart')"
        />
    </div>
</template>
