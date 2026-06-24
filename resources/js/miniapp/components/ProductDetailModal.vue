<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { ChevronRight, Minus, Package, Plus, ShoppingCart, Tag, X } from 'lucide-vue-next';
import ImageLightbox from '@/components/ImageLightbox.vue';
import { getTg } from '../composables/useApi';
import { useCartStore } from '../composables/useCartStore';

const { t } = useI18n();

type ProductImage = { id: number; url: string; sort_order: number };

export type ProductTypeInfo = {
    id: number;
    product_id?: number;
    name: string;
    code: string | null;
    price: number;
    original_price?: number | null;
    pack_size: number;
    pack_price: number;
    bulk_only?: boolean;
    is_active?: boolean;
    images: ProductImage[];
    image_url?: string | null;
};

export type ProductInfo = {
    id: number;
    name: string;
    description: string | null;
    price: number;
    original_price?: number | null;
    discount_percent?: number;
    unit: string;
    image_url: string | null;
    images: ProductImage[];
    pack_size: number;
    pack_price: number;
    bulk_only?: boolean;
    has_types?: boolean;
    starting_price?: number;
    types?: ProductTypeInfo[];
};

export type CartItemState = {
    qty: number;
    pack_qty: number | null;
    product_type_id?: number | null;
};

const props = withDefaults(defineProps<{
    product: ProductInfo | null;
    open: boolean;
    showAddToCart?: boolean;
    cartItem?: CartItemState | null;
    allowGoToCart?: boolean;
}>(), {
    showAddToCart: true,
    cartItem: null,
    allowGoToCart: true,
});

const emit = defineEmits<{
    close: [];
    goToCart: [];
}>();

const cartStore = useCartStore();
const activeImageIdx = ref(0);
const errorMsg = ref('');

const lightboxOpen = ref(false);
const lightboxIdx = ref(0);

const selectedTypeId = ref<number | null>(null);

const showFullDescription = ref(false);
const DESCRIPTION_COLLAPSE_THRESHOLD = 120;

const isDescriptionLong = computed<boolean>(() => {
    const desc = props.product?.description ?? '';

    return desc.length > DESCRIPTION_COLLAPSE_THRESHOLD || desc.includes('\n');
});

const hasTypes = computed(() => Boolean(props.product?.has_types) && (props.product?.types?.length ?? 0) > 0);

const activeTypes = computed<ProductTypeInfo[]>(() => {
    return (props.product?.types ?? []).filter((t) => t.is_active !== false);
});

const selectedType = computed<ProductTypeInfo | null>(() => {
    if (!hasTypes.value) {
        return null;
    }

    return activeTypes.value.find((t) => t.id === selectedTypeId.value) ?? null;
});

const effectivePrice = computed<number>(() => selectedType.value?.price ?? props.product?.price ?? 0);
const effectiveOriginalPrice = computed<number | null>(() =>
    selectedType.value?.original_price ?? props.product?.original_price ?? null,
);
const effectivePackSize = computed<number>(() => Math.max(1, selectedType.value?.pack_size ?? props.product?.pack_size ?? 1));
const effectivePackPrice = computed<number>(() => effectivePrice.value * effectivePackSize.value);
const effectiveBulkOnly = computed<boolean>(() => Boolean(selectedType.value?.bulk_only ?? props.product?.bulk_only));
const effectiveUnit = computed<string>(() => props.product?.unit ?? 'dona');

const images = computed<string[]>(() => {
    // Type tanlangan bo'lsa va type rasmi bor — type rasmini ko'rsatamiz
    const fromType = selectedType.value?.images;

    if (fromType && fromType.length > 0) {
        return fromType.map((i) => i.url);
    }

    const p = props.product;

    if (!p) {
        return [];
    }

    if (p.images && p.images.length > 0) {
        return p.images.map((i) => i.url);
    }

    if (p.image_url) {
        return [p.image_url];
    }

    return [];
});

const currentCart = computed<CartItemState | null>(() => {
    const p = props.product;

    if (!p) {
        return null;
    }

    const item = cartStore.findItem(p.id, selectedTypeId.value);

    if (!item) {
        return null;
    }

    return {
        qty: item.qty,
        pack_qty: item.pack_qty ?? null,
        product_type_id: item.product_type_id ?? null,
    };
});

const inCart = computed(() => currentCart.value !== null);

const packSize = computed(() => effectivePackSize.value);
const hasPacks = computed(() => packSize.value > 1);
const bulkOnly = computed(() => effectiveBulkOnly.value && hasPacks.value);

const packQty = computed<number>(() => currentCart.value?.pack_qty ?? 0);

const unitQty = computed<number>(() => {
    const c = currentCart.value;

    if (!c) {
        return 0;
    }

    return Math.max(0, c.qty - (c.pack_qty ?? 0) * packSize.value);
});

const totalAmount = computed<number>(() => {
    const c = currentCart.value;

    if (!c) {
        return 0;
    }

    return c.qty * effectivePrice.value;
});

watch([() => props.product, () => props.open], ([, open]) => {
    if (!open) {
        return;
    }

    activeImageIdx.value = 0;
    errorMsg.value = '';
    showFullDescription.value = false;

    // Type tanlash boshlang'ich qiymati: cartItem berilgan bo'lsa undan, aks holda 1-tip
    if (hasTypes.value) {
        const fromCart = props.cartItem?.product_type_id ?? null;

        if (fromCart && activeTypes.value.some((t) => t.id === fromCart)) {
            selectedTypeId.value = fromCart;
        } else {
            selectedTypeId.value = activeTypes.value[0]?.id ?? null;
        }
    } else {
        selectedTypeId.value = null;
    }
});

watch(selectedTypeId, () => {
    activeImageIdx.value = 0;
});

function openLightbox(idx: number) {
    lightboxIdx.value = idx;
    lightboxOpen.value = true;
}

async function applyState(nextPackQty: number, nextUnitQty: number): Promise<void> {
    if (!props.product) {
        return;
    }

    if (hasTypes.value && selectedTypeId.value === null) {
        errorMsg.value = t('miniappPages.productDetail.selectType');

        return;
    }

    errorMsg.value = '';

    const safePack = Math.max(0, nextPackQty);
    const safeUnit = bulkOnly.value ? 0 : Math.max(0, nextUnitQty);
    const totalQty = safePack * packSize.value + safeUnit;
    const typeId = selectedTypeId.value;

    if (totalQty <= 0) {
        if (inCart.value) {
            cartStore.remove(props.product.id, typeId);
        }

        return;
    }

    const payload = { qty: totalQty, pack_qty: safePack, product_type_id: typeId };

    if (inCart.value) {
        cartStore.updateQtyOptimistic(props.product.id, payload);

        return;
    }

    try {
        await cartStore.add(
            {
                id: props.product.id,
                name: props.product.name,
                price: effectivePrice.value,
                unit: effectiveUnit.value,
                pack_size: packSize.value,
                bulk_only: bulkOnly.value,
                product_type_id: typeId,
                product_type_name: selectedType.value?.name ?? null,
            },
            { qty: totalQty, pack_qty: safePack },
        );
    } catch (e: any) {
        errorMsg.value = e.message;
    }
}

function changePackQty(delta: number): void {
    void applyState(packQty.value + delta, unitQty.value);
}

function changeUnitQty(delta: number): void {
    void applyState(packQty.value, unitQty.value + delta);
}

function setPackQty(raw: string): void {
    const n = parseInt(raw, 10);
    void applyState(isNaN(n) ? 0 : Math.max(0, n), unitQty.value);
}

function setUnitQty(raw: string): void {
    const n = parseInt(raw, 10);
    void applyState(packQty.value, isNaN(n) ? 0 : Math.max(0, n));
}

function selectType(typeId: number) {
    selectedTypeId.value = typeId;
}

function goToCart() {
    emit('goToCart');
    emit('close');
}

function formatMoney(n: number): string {
    return String(Math.round(n)).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
}

// ───────────────── Telegram native UI integration ─────────────────
const tg = getTg();
const useNativeButtons = computed(() => Boolean(tg?.MainButton));

let mainButtonHandler: (() => void) | null = null;
let backButtonHandler: (() => void) | null = null;

function syncMainButton(): void {
    tg?.MainButton?.hide?.();
}

function syncBackButton(): void {
    if (!tg?.BackButton) {
        return;
    }

    if (props.open) {
        tg.BackButton.show?.();
    } else {
        tg.BackButton.hide?.();
    }
}

onMounted(() => {
    if (!tg) {
        return;
    }

    mainButtonHandler = () => {
        if (inCart.value && props.allowGoToCart) {
            goToCart();
        }
    };
    backButtonHandler = () => emit('close');

    tg.MainButton?.onClick?.(mainButtonHandler);
    tg.BackButton?.onClick?.(backButtonHandler);
});

onBeforeUnmount(() => {
    if (!tg) {
        return;
    }

    if (mainButtonHandler) {
        tg.MainButton?.offClick?.(mainButtonHandler);
    }

    if (backButtonHandler) {
        tg.BackButton?.offClick?.(backButtonHandler);
    }

    tg.MainButton?.hide?.();
    tg.BackButton?.hide?.();
});

watch(
    [() => props.open, () => props.showAddToCart, () => props.allowGoToCart, inCart, hasPacks],
    () => {
        syncMainButton();
        syncBackButton();
    },
    { immediate: true },
);
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition-opacity duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="open && product"
                class="fixed inset-0 z-50 flex items-stretch justify-center bg-black/70 sm:items-center sm:p-4"
                @click.self="emit('close')"
            >
                <Transition
                    enter-active-class="transition-transform duration-200 ease-out"
                    enter-from-class="translate-y-full sm:translate-y-4 sm:opacity-0"
                    enter-to-class="translate-y-0 sm:opacity-100"
                    leave-active-class="transition-transform duration-150 ease-in"
                    leave-from-class="translate-y-0"
                    leave-to-class="translate-y-full"
                    appear
                >
                    <div
                        class="flex h-full w-full flex-col overflow-hidden bg-secondary shadow-2xl sm:h-auto sm:max-h-[90vh] sm:max-w-xl sm:rounded-3xl"
                        style="will-change: transform; transform: translateZ(0);"
                    >
                        <!-- Scrollable content -->
                        <div class="flex min-h-0 flex-1 flex-col overflow-y-auto">
                            <!-- Image area -->
                            <div class="relative shrink-0 bg-muted">
                                <button
                                    v-if="!useNativeButtons"
                                    class="absolute right-3 top-3 z-10 flex h-10 w-10 items-center justify-center rounded-full bg-black/50 text-white backdrop-blur-sm transition-colors hover:bg-black/70"
                                    :aria-label="t('miniappPages.productDetail.closeAria')"
                                    @click="emit('close')"
                                >
                                    <X class="h-5 w-5" />
                                </button>

                                <div
                                    v-if="product.discount_percent && product.discount_percent > 0"
                                    class="absolute left-3 top-3 z-10 inline-flex items-center gap-1 rounded-full bg-rose-500 px-2.5 py-1 text-xs font-bold text-white shadow"
                                >
                                    <Tag class="h-3 w-3" />
                                    −{{ product.discount_percent }}%
                                </div>

                                <template v-if="images.length > 0">
                                    <div class="relative flex h-[32vh] max-h-[320px] min-h-[200px] w-full items-center justify-center overflow-hidden sm:h-[38vh]">
                                        <img
                                            :src="images[activeImageIdx]"
                                            :alt="product.name"
                                            class="h-full w-full cursor-zoom-in object-contain"
                                            @click="openLightbox(activeImageIdx)"
                                        />
                                        <!-- Dots indicator -->
                                        <div
                                            v-if="images.length > 1"
                                            class="absolute bottom-3 left-1/2 flex -translate-x-1/2 gap-1.5"
                                        >
                                            <button
                                                v-for="(_, i) in images"
                                                :key="i"
                                                type="button"
                                                class="h-1.5 rounded-full bg-white/60 transition-all"
                                                :class="activeImageIdx === i ? 'w-6 bg-white' : 'w-1.5 hover:bg-white/80'"
                                                :aria-label="t('miniappPages.productDetail.imageAria', { n: i + 1 })"
                                                @click="activeImageIdx = i"
                                            />
                                        </div>
                                    </div>
                                </template>
                                <div v-else class="flex h-[32vh] max-h-[320px] min-h-[200px] w-full items-center justify-center text-muted-foreground/30 sm:h-[38vh]">
                                    <Package class="h-16 w-16" />
                                </div>
                            </div>

                            <!-- Telegram-style grouped sections -->
                            <div class="flex flex-col gap-4 px-3 py-4 sm:px-4">
                                <!-- Sarlavha + narx (1-section) -->
                                <section class="rounded-2xl bg-background px-4 py-3.5">
                                    <h3 class="text-xl font-bold leading-tight tracking-tight sm:text-2xl">{{ product.name }}</h3>
                                    <div class="mt-1.5 flex items-baseline gap-2">
                                        <span
                                            class="text-2xl font-bold tracking-tight sm:text-3xl"
                                            :class="effectiveOriginalPrice ? 'text-rose-600 dark:text-rose-400' : ''"
                                        >
                                            {{ formatMoney(effectivePrice) }}
                                        </span>
                                        <span class="text-sm font-medium text-foreground">{{ t('miniappPages.productDetail.perUnit', { unit: effectiveUnit }) }}</span>
                                        <span v-if="effectiveOriginalPrice" class="ml-1 text-sm text-foreground line-through">
                                            {{ formatMoney(effectiveOriginalPrice) }}
                                        </span>
                                    </div>
                                    <p v-if="hasPacks" class="mt-0.5 text-xs text-foreground">
                                        {{ t('miniappPages.productDetail.blockLine', { size: packSize, unit: effectiveUnit, price: formatMoney(effectivePackPrice) }) }}
                                    </p>
                                </section>

                                <!-- Type selector (label yo'q, gorizontal scroll) -->
                                <section v-if="hasTypes" class="-mx-3 sm:-mx-4">
                                    <div class="overflow-x-auto px-3 sm:px-4">
                                        <div class="flex gap-2 pb-1">
                                            <button
                                                v-for="t in activeTypes"
                                                :key="t.id"
                                                type="button"
                                                class="shrink-0 rounded-full border px-4 py-2 text-sm font-medium transition-colors"
                                                :class="selectedTypeId === t.id
                                                    ? 'border-primary bg-primary text-primary-foreground'
                                                    : 'border-transparent bg-background text-foreground'"
                                                @click="selectType(t.id)"
                                            >
                                                {{ t.name }}<span v-if="t.code" class="ml-1 opacity-70">· {{ t.code }}</span>
                                            </button>
                                        </div>
                                    </div>
                                </section>

                                <!-- Bulk-only notice -->
                                <section v-if="props.showAddToCart && bulkOnly" class="flex items-center gap-2 rounded-2xl bg-amber-500/15 px-4 py-2.5 text-sm">
                                    <Package class="h-4 w-4 shrink-0 text-amber-700 dark:text-amber-400" />
                                    <span class="font-medium text-amber-700 dark:text-amber-300">{{ t('miniappPages.productDetail.bulkOnly') }}</span>
                                </section>

                                <!-- Quantity steppers (Telegram-style grouped list) -->
                                <section v-if="props.showAddToCart" class="overflow-hidden rounded-2xl bg-background">
                                    <div v-if="hasPacks" class="flex items-center justify-between gap-3 px-4 py-2.5" :class="!bulkOnly ? 'border-b border-border/50' : ''">
                                        <div class="min-w-0 flex-1">
                                            <p class="text-base font-medium">{{ t('miniappPages.productDetail.blockLabel') }}</p>
                                            <p class="text-base text-foreground">{{ t('miniappPages.productDetail.blockSub', { size: packSize, unit: effectiveUnit, price: formatMoney(effectivePackPrice) }) }}</p>
                                        </div>
                                        <div class="flex items-center gap-1">
                                            <button
                                                type="button"
                                                :disabled="packQty <= 0"
                                                class="flex h-9 w-9 items-center justify-center rounded-full bg-secondary text-foreground transition-opacity disabled:opacity-30"
                                                @click="changePackQty(-1)"
                                            >
                                                <Minus class="h-4 w-4" />
                                            </button>
                                            <input
                                                type="number"
                                                inputmode="numeric"
                                                pattern="[0-9]*"
                                                min="0"
                                                :value="packQty"
                                                class="w-10 min-w-0 bg-transparent text-center text-base font-bold tabular-nums outline-none [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                                                @input="setPackQty(($event.target as HTMLInputElement).value)"
                                            />
                                            <button
                                                type="button"
                                                class="flex h-9 w-9 items-center justify-center rounded-full bg-primary text-primary-foreground transition-opacity"
                                                @click="changePackQty(1)"
                                            >
                                                <Plus class="h-4 w-4" />
                                            </button>
                                        </div>
                                    </div>

                                    <div v-if="!bulkOnly" class="flex items-center justify-between gap-3 px-4 py-2.5">
                                        <div class="min-w-0 flex-1">
                                            <p class="text-base font-medium capitalize">{{ effectiveUnit }}</p>
                                            <p class="text-[13px] text-foreground">
                                                {{ t('miniappPages.productDetail.unitSub', { unit: effectiveUnit, price: formatMoney(effectivePrice) }) }}
                                            </p>
                                        </div>
                                        <div class="flex items-center gap-1">
                                            <button
                                                type="button"
                                                :disabled="unitQty <= 0"
                                                class="flex h-9 w-9 items-center justify-center rounded-full bg-secondary text-foreground transition-opacity disabled:opacity-30"
                                                @click="changeUnitQty(-1)"
                                            >
                                                <Minus class="h-4 w-4" />
                                            </button>
                                            <input
                                                type="number"
                                                inputmode="numeric"
                                                pattern="[0-9]*"
                                                min="0"
                                                :value="unitQty"
                                                class="w-10 min-w-0 bg-transparent text-center text-base font-bold tabular-nums outline-none [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                                                @input="setUnitQty(($event.target as HTMLInputElement).value)"
                                            />
                                            <button
                                                type="button"
                                                class="flex h-9 w-9 items-center justify-center rounded-full bg-primary text-primary-foreground transition-opacity"
                                                @click="changeUnitQty(1)"
                                            >
                                                <Plus class="h-4 w-4" />
                                            </button>
                                        </div>
                                    </div>
                                </section>

                                <!-- Savat holati (Telegram grouped row) -->
                                <section v-if="props.showAddToCart && inCart" class="rounded-2xl bg-background px-4 py-3">
                                    <div class="flex items-center justify-between gap-2 text-sm">
                                        <span class="text-foreground">
                                            {{ t('miniappPages.productDetail.inCart') }}
                                            <span class="font-semibold text-foreground">
                                                <template v-if="hasPacks && packQty > 0 && unitQty > 0">
                                                    {{ t('miniappPages.productDetail.inCartCombo', { packs: packQty, units: unitQty, unit: effectiveUnit }) }}
                                                </template>
                                                <template v-else-if="hasPacks && packQty > 0">
                                                    {{ t('miniappPages.productDetail.inCartPacks', { packs: packQty, total: packQty * packSize, unit: effectiveUnit }) }}
                                                </template>
                                                <template v-else>
                                                    {{ t('miniappPages.productDetail.inCartUnits', { units: unitQty, unit: effectiveUnit }) }}
                                                </template>
                                            </span>
                                        </span>
                                        <span class="text-base font-bold tracking-tight">{{ formatMoney(totalAmount) }}&nbsp;<span class="text-xs font-medium text-foreground">so'm</span></span>
                                    </div>
                                </section>

                                <p v-if="errorMsg" class="flex items-center justify-center gap-1.5 rounded-2xl bg-rose-500/15 px-4 py-2.5 text-sm text-rose-700 dark:text-rose-300">
                                    <X class="h-4 w-4 shrink-0" />
                                    {{ errorMsg }}
                                </p>

                                <!-- Tavsif (alohida section, kollapsiruvchi) -->
                                <section v-if="product.description" class="rounded-2xl bg-background px-4 py-3.5">
                                    <p class="text-[11px] font-medium uppercase tracking-wider text-foreground">{{ t('miniappPages.productDetail.description') }}</p>
                                    <p
                                        class="mt-1.5 whitespace-pre-line text-[15px] leading-relaxed text-foreground"
                                        :class="!showFullDescription && isDescriptionLong ? 'line-clamp-3' : ''"
                                    >{{ product.description }}</p>
                                    <button
                                        v-if="isDescriptionLong"
                                        type="button"
                                        class="mt-2 inline-flex items-center gap-0.5 text-sm font-semibold text-primary"
                                        @click="showFullDescription = !showFullDescription"
                                    >
                                        <span>{{ showFullDescription ? t('miniappPages.productDetail.showLess') : t('miniappPages.productDetail.showMore') }}</span>
                                        <ChevronRight class="h-3.5 w-3.5 transition-transform" :class="showFullDescription ? '-rotate-90' : ''" />
                                    </button>
                                </section>
                            </div>
                        </div>

                        <!-- Sticky bottom CTA: empty=continue, inCart=split 50/50 -->
                        <div
                            v-if="props.showAddToCart"
                            class="shrink-0 bg-secondary px-3 pb-[calc(env(safe-area-inset-bottom)+12px)] pt-2 sm:px-4 sm:pb-4"
                        >
                            <div v-if="inCart && props.allowGoToCart" class="grid grid-cols-2 gap-2">
                                <button
                                    type="button"
                                    class="flex w-full items-center justify-center gap-1.5 truncate whitespace-nowrap rounded-2xl bg-background py-3.5 text-sm font-semibold text-foreground transition-colors active:scale-[0.99]"
                                    @click="emit('close')"
                                >
                                    {{ t('miniappPages.productDetail.continueShopping') }}
                                </button>
                                <button
                                    type="button"
                                    class="flex w-full items-center justify-center gap-1.5 truncate whitespace-nowrap rounded-2xl bg-primary py-3.5 text-sm font-semibold text-primary-foreground transition-all active:scale-[0.99]"
                                    @click="goToCart"
                                >
                                    <ShoppingCart class="h-4 w-4 shrink-0" />
                                    <span class="truncate">{{ t('miniappPages.productDetail.goToCart') }}</span>
                                </button>
                            </div>
                            <button
                                v-else
                                type="button"
                                class="flex w-full items-center justify-center gap-2 whitespace-nowrap rounded-2xl bg-primary py-3.5 text-base font-semibold text-primary-foreground transition-all active:scale-[0.99]"
                                @click="emit('close')"
                            >
                                {{ t('miniappPages.productDetail.continueShopping') }}
                            </button>
                        </div>
                    </div>
                </Transition>
            </div>
        </Transition>

        <ImageLightbox
            :images="images"
            :initial-idx="lightboxIdx"
            :open="lightboxOpen"
            disable-double-tap
            @close="lightboxOpen = false"
        />
    </Teleport>
</template>
