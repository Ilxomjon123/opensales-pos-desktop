<script setup lang="ts">
import { Head, InfiniteScroll, router } from '@inertiajs/vue3';
import { Search, ShoppingCart, Store } from 'lucide-vue-next';
import { onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import MarketplaceCart from '@/components/marketplace/MarketplaceCart.vue';
import MarketplaceProductModal from '@/components/marketplace/MarketplaceProductModal.vue';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { SearchableSelect } from '@/components/ui/searchable-select';
import { Spinner } from '@/components/ui/spinner';
import {
    totalUnits,
    useMarketplaceCart,
} from '@/composables/useMarketplaceCart';
import type { MarketplaceProduct } from '@/composables/useMarketplaceCart';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatMoneySum } from '@/lib/format';
import type { Paginated } from '@/types';

const { t } = useI18n();

const props = defineProps<{
    products: Paginated<MarketplaceProduct>;
    sellers: { id: number; name: string }[];
    categories: { name: string }[];
    filters: {
        search?: string;
        seller_id?: number | null;
        category?: string | null;
    };
}>();

const search = ref(props.filters.search ?? '');
const sellerId = ref<number | null>(props.filters.seller_id ?? null);
const category = ref<string | null>(props.filters.category ?? null);

function applyFilters() {
    router.get(
        '/dealer/marketplace',
        {
            search: search.value || undefined,
            seller_id: sellerId.value || undefined,
            category: category.value || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

let searchTimer: ReturnType<typeof setTimeout> | null = null;
watch(search, () => {
    if (searchTimer) {
        clearTimeout(searchTimer);
    }

    searchTimer = setTimeout(applyFilters, 300);
});

function selectCategory(name: string | null) {
    category.value = name;
    applyFilters();
}

// Sotuvchi o'zgarsa tanlangan kategoriya yangi sotuvchida bo'lmasligi mumkin -> reset.
function onSellerChange() {
    category.value = null;
    applyFilters();
}

// --- Savat ---
const cart = useMarketplaceCart();

// Mobil savat (bottom-sheet). Desktop'da sidebar doim ko'rinadi.
const isMounted = ref(false);
onMounted(() => (isMounted.value = true));
const sheetOpen = ref(false);

function openCartSheet() {
    sheetOpen.value = true;
}

// --- Modal ---
const selected = ref<MarketplaceProduct | null>(null);
const modalOpen = ref(false);

function openProduct(p: MarketplaceProduct) {
    selected.value = p;
    modalOpen.value = true;
}

function cartUnits(productId: number): number {
    const line = cart.lineOf(productId);

    return line ? totalUnits(line) : 0;
}

function goToCart() {
    modalOpen.value = false;

    // Mobil: savat sheet'ini ochamiz; desktop'da sidebar allaqachon ko'rinadi.
    if (window.matchMedia('(max-width: 1023px)').matches) {
        sheetOpen.value = true;
    }
}

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head :title="t('pageDealer.marketplace.title')" />

    <div class="flex flex-col gap-4 p-4 sm:gap-6 sm:p-6">
        <!-- Sarlavha -->
        <div class="flex items-center gap-2.5">
            <div class="rounded-md bg-primary/10 p-1.5 text-primary">
                <Store class="h-5 w-5" />
            </div>
            <div>
                <h1 class="text-lg font-bold tracking-tight sm:text-xl">
                    {{ t('pageDealer.marketplace.title') }}
                </h1>
                <p class="text-xs text-muted-foreground">
                    {{ t('pageDealer.marketplace.subtitle') }}
                </p>
            </div>
        </div>

        <div
            class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_300px] xl:grid-cols-[minmax(0,1fr)_340px] xl:gap-6"
        >
            <!-- Katalog -->
            <div class="min-w-0">
                <!-- Filtrlar -->
                <div class="mb-3 flex flex-col gap-2 sm:flex-row">
                    <div class="relative flex-1">
                        <Search
                            class="absolute top-2.5 left-2.5 h-4 w-4 text-muted-foreground"
                        />
                        <Input
                            v-model="search"
                            :placeholder="
                                t('pageDealer.marketplace.searchPlaceholder')
                            "
                            class="pl-9"
                        />
                    </div>
                    <SearchableSelect
                        v-model="sellerId"
                        :items="sellers"
                        value-key="id"
                        label-key="name"
                        :placeholder="t('pageDealer.marketplace.allSellers')"
                        class="sm:w-56"
                        @update:model-value="onSellerChange"
                    />
                </div>

                <!-- Kategoriya chiplari -->
                <div
                    v-if="categories.length"
                    class="mb-3 flex gap-2 overflow-x-auto pb-1"
                >
                    <button
                        type="button"
                        class="shrink-0 rounded-full border px-3 py-1.5 text-xs font-medium transition-colors"
                        :class="
                            category === null
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'border-border bg-background text-foreground/80 hover:bg-muted'
                        "
                        @click="selectCategory(null)"
                    >
                        {{ t('pageDealer.marketplace.categoryAll') }}
                    </button>
                    <button
                        v-for="c in categories"
                        :key="c.name"
                        type="button"
                        class="shrink-0 rounded-full border px-3 py-1.5 text-xs font-medium transition-colors"
                        :class="
                            category === c.name
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'border-border bg-background text-foreground/80 hover:bg-muted'
                        "
                        @click="selectCategory(c.name)"
                    >
                        {{ c.name }}
                    </button>
                </div>

                <!-- Bo'sh -->
                <div
                    v-if="products.data.length === 0"
                    class="rounded-lg border border-dashed p-10 text-center text-sm text-muted-foreground"
                >
                    {{ t('pageDealer.marketplace.empty') }}
                </div>

                <!-- Mahsulotlar (dealer/products kabi zich grid) -->
                <InfiniteScroll
                    v-else
                    data="products"
                    :buffer="400"
                    class="grid grid-cols-2 gap-2.5 sm:grid-cols-3 sm:gap-3 md:grid-cols-4 xl:grid-cols-5"
                >
                    <Card
                        v-for="p in products.data"
                        :key="p.id"
                        class="group flex cursor-pointer flex-col gap-0 overflow-hidden py-0 transition-all hover:border-primary/50 hover:shadow-md"
                        @click="openProduct(p)"
                    >
                        <div
                            class="relative aspect-square w-full overflow-hidden bg-muted"
                        >
                            <img
                                v-if="p.image_url"
                                :src="p.image_url"
                                loading="lazy"
                                class="h-full w-full object-cover transition-transform group-hover:scale-105"
                            />
                            <div
                                v-else
                                class="flex h-full items-center justify-center text-muted-foreground/40"
                            >
                                <Store class="h-8 w-8" />
                            </div>
                            <span
                                v-if="p.images.length > 1"
                                class="absolute top-1.5 right-1.5 rounded bg-black/50 px-1.5 py-0.5 text-[10px] text-white"
                            >
                                +{{ p.images.length - 1 }}
                            </span>
                            <span
                                v-if="cartUnits(p.id) > 0"
                                class="absolute right-2 bottom-2 flex h-6 min-w-6 items-center justify-center rounded-full bg-emerald-500 px-1.5 text-xs font-bold text-white shadow-md ring-2 ring-background tabular-nums"
                                >{{ cartUnits(p.id) }}</span
                            >
                        </div>
                        <div class="flex flex-1 flex-col gap-0.5 p-2.5">
                            <p
                                class="line-clamp-2 text-sm leading-tight font-semibold"
                                :title="p.name"
                            >
                                {{ p.name }}
                            </p>
                            <p class="text-[11px] text-muted-foreground">
                                {{ p.seller.name }}
                            </p>
                            <div class="mt-auto pt-1">
                                <p class="text-base font-bold text-primary">
                                    {{ formatMoneySum(p.price, p.currency) }}
                                    <span
                                        class="text-[10px] font-normal text-muted-foreground"
                                        >/ {{ p.unit_label }}</span
                                    >
                                </p>
                                <p
                                    v-if="p.pack_price && p.pack_size > 1"
                                    class="text-[10px] text-muted-foreground"
                                >
                                    {{
                                        t('pageDealer.marketplace.blockPrice', {
                                            price: formatMoneySum(
                                                p.pack_price,
                                                p.currency,
                                            ),
                                        })
                                    }}
                                </p>
                            </div>
                        </div>
                    </Card>
                    <template #loading>
                        <div class="col-span-full flex justify-center py-4">
                            <Spinner />
                        </div>
                    </template>
                </InfiniteScroll>
            </div>

            <!-- Savat (desktop sidebar) — yon tomonda sticky; max-h ekranga moslaydi,
                 mahsulot ko'p bo'lsa ichi scroll, total+submit doim ko'rinadi -->
            <div class="hidden lg:sticky lg:top-4 lg:block lg:self-start">
                <MarketplaceCart class="lg:max-h-[calc(100dvh-7rem)]" />
            </div>
        </div>
    </div>

    <!-- Mobil: pastda suzuvchi savat tugmasi -->
    <button
        v-if="!cart.isEmpty.value"
        type="button"
        class="fixed inset-x-3 bottom-[calc(4.5rem+env(safe-area-inset-bottom))] z-40 flex items-center justify-between gap-3 rounded-2xl bg-primary px-4 py-3 text-primary-foreground shadow-xl transition-transform active:scale-[0.99] lg:hidden"
        @click="openCartSheet"
    >
        <span class="flex items-center gap-2">
            <ShoppingCart class="h-5 w-5" />
            <span class="text-sm font-semibold">{{
                t('pageDealer.marketplace.cart')
            }}</span>
            <span
                class="flex h-5 min-w-5 items-center justify-center rounded-full bg-primary-foreground/20 px-1.5 text-xs font-bold tabular-nums"
                >{{ cart.count.value }}</span
            >
        </span>
        <span class="text-sm font-bold">{{
            formatMoneySum(
                cart.total.value,
                cart.lines.value[0]?.product.currency,
            )
        }}</span>
    </button>

    <!-- Mobil: savat bottom-sheet -->
    <Teleport to="body" :disabled="!isMounted">
        <Transition
            enter-active-class="transition-opacity duration-200"
            leave-active-class="transition-opacity duration-200"
            enter-from-class="opacity-0"
            leave-to-class="opacity-0"
        >
            <div
                v-if="sheetOpen"
                class="fixed inset-0 z-50 flex items-end bg-black/60 lg:hidden"
                @click.self="sheetOpen = false"
            >
                <Transition
                    enter-active-class="transition-transform duration-300 ease-out"
                    leave-active-class="transition-transform duration-200 ease-in"
                    enter-from-class="translate-y-full"
                    leave-to-class="translate-y-full"
                    appear
                >
                    <MarketplaceCart
                        show-close
                        class="max-h-[85vh] w-full rounded-t-2xl rounded-b-none"
                        @close="sheetOpen = false"
                    />
                </Transition>
            </div>
        </Transition>
    </Teleport>

    <MarketplaceProductModal
        :product="selected"
        :open="modalOpen"
        @close="modalOpen = false"
        @go-to-cart="goToCart"
    />
</template>
